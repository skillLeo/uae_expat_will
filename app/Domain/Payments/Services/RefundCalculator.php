<?php

namespace App\Domain\Payments\Services;

use App\Domain\Cases\Enums\CaseStage;
use App\Domain\Payments\Enums\PaymentType;
use App\Domain\Payments\Enums\RefundBand;
use App\Domain\Payments\Exceptions\DisbursementNeedsDocumentedRefund;
use App\Models\FeeAllocation;
use App\Models\LegalCase;
use App\Models\Payment;

/**
 * Works out the refund band and amount.
 *
 * The band comes ONLY from case_stage_timestamps. Not from the case status,
 * which moves back and forth; not from anyone's view of how much work was done.
 * The stage timestamps are the evidence, and the full working is stored on the
 * refund so the figure can still be justified a year later.
 *
 * Bands apply to Summit's professional fee and nothing else. A disbursement —
 * a court, registry or notary charge collected on somebody else's behalf — has
 * no band at all: it is refundable only to the extent it is actually
 * recoverable, which is a fact about the third party that no rule here can
 * know. That path demands a documented figure and a documented reason, and
 * stores the same working as every other refund.
 */
class RefundCalculator
{
    /**
     * @return array{
     *     band: RefundBand|null,
     *     refundable: float,
     *     deduction: float,
     *     reason: string,
     *     calculation: array<string, mixed>
     * }
     *
     * @throws DisbursementNeedsDocumentedRefund when handed a disbursement with
     *                                           no documented figure and reason.
     */
    public function calculate(
        Payment $payment,
        ?float $documentedDeduction = null,
        ?string $documentedReason = null,
    ): array {
        if ($payment->type === PaymentType::Disbursement) {
            return $this->disbursement($payment, $documentedDeduction, $documentedReason);
        }

        $case = $payment->legalCase;
        $paid = (float) $payment->total_amount;
        $band = $this->band($case);

        $reached = $this->stagesReached($case);

        [$refundable, $deduction, $reason, $workings] = match ($band) {
            RefundBand::A => [
                $paid, 0.0,
                'No substantive work had started, so the fee is refunded in full.',
                [],
            ],

            RefundBand::B => $this->bandB($paid, $documentedDeduction),

            RefundBand::C => $this->bandC($case, $paid),

            RefundBand::D => [
                0.0, $paid,
                'Final approval was recorded, a third-party cost was committed, or the '
                .'matter was submitted to the authority. The professional fee is not refundable.',
                [],
            ],
        };

        return [
            'band' => $band,
            'refundable' => round($refundable, 2),
            'deduction' => round($deduction, 2),
            'reason' => $reason,
            'calculation' => [
                'band' => $band->value,
                'band_description' => $band->description(),
                'payment_type' => PaymentType::ProfessionalFee->value,
                'total_paid' => $paid,
                'stages_reached' => $reached,
                'refundable' => round($refundable, 2),
                'deduction' => round($deduction, 2),
                'workings' => $workings,
                'calculated_at' => now()->toIso8601String(),
            ],
        ];
    }

    /**
     * A disbursement refund.
     *
     * `$recoverable` is what the authority or third party will actually give
     * back — nil once a registration is lodged, sometimes the whole amount if
     * nothing was submitted. It is a fact a person has to establish and record,
     * not something derivable from a stage timestamp.
     *
     * @return array{band: null, refundable: float, deduction: float, reason: string, calculation: array<string, mixed>}
     */
    private function disbursement(Payment $payment, ?float $recoverable, ?string $reason): array
    {
        if ($recoverable === null || trim((string) $reason) === '') {
            throw DisbursementNeedsDocumentedRefund::make();
        }

        $paid = (float) $payment->total_amount;
        $refundable = round(min(max($recoverable, 0.0), $paid), 2);

        return [
            'band' => null,
            'refundable' => $refundable,
            'deduction' => round($paid - $refundable, 2),
            'reason' => $reason,
            'calculation' => [
                'band' => null,
                'band_description' => 'Not banded. The refund bands describe Summit\'s professional fee; '
                    .'this payment is a charge collected on behalf of an authority or third party.',
                'payment_type' => PaymentType::Disbursement->value,
                'stage_label' => $payment->stage_label,
                'total_paid' => $paid,
                'stages_reached' => $this->stagesReached($payment->legalCase),
                'refundable' => $refundable,
                'deduction' => round($paid - $refundable, 2),
                'workings' => [
                    'source' => 'documented',
                    'documented_recoverable' => round((float) $recoverable, 2),
                    'documented_reason' => $reason,
                    'capped_at_amount_paid' => $recoverable > $paid,
                ],
                'calculated_at' => now()->toIso8601String(),
            ],
        ];
    }

    /** Highest band first — the furthest stage reached is the one that governs. */
    public function band(LegalCase $case): RefundBand
    {
        foreach ([RefundBand::D, RefundBand::C, RefundBand::B] as $band) {
            foreach ($band->triggeringStages() as $stage) {
                if ($case->hasReachedStage($stage)) {
                    return $band;
                }
            }
        }

        return RefundBand::A;
    }

    /** @return array{0: float, 1: float, 2: string, 3: array<string, mixed>} */
    private function bandB(float $paid, ?float $documentedDeduction): array
    {
        // With no documented figure, fall back to the fee allocated to the stages
        // actually performed. A deduction has to be justifiable, so it is never
        // an arbitrary percentage.
        $deduction = $documentedDeduction ?? $this->allocationFor('substantive_work_started', $paid);
        $deduction = min($deduction, $paid);

        return [
            $paid - $deduction,
            $deduction,
            'Substantive work had started but no draft had been delivered. A reasonable, '
            .'documented amount for the work completed has been deducted.',
            ['deduction_source' => $documentedDeduction !== null ? 'documented' : 'fee_allocation'],
        ];
    }

    /** @return array{0: float, 1: float, 2: string, 3: array<string, mixed>} */
    private function bandC(LegalCase $case, float $paid): array
    {
        // The unused portion: everything allocated to stages NOT yet reached.
        $allocations = FeeAllocation::where('service_type', $case->service_type ?? 'standard_will')->get();

        if ($allocations->isEmpty()) {
            return [
                0.0, $paid,
                'A first draft had been delivered. No fee allocation is configured, so no '
                .'unused portion could be computed.',
                ['warning' => 'fee_allocations table is empty for this service type'],
            ];
        }

        $unusedPercent = 0.0;
        $breakdown = [];

        foreach ($allocations as $allocation) {
            $stage = CaseStage::tryFrom($allocation->stage);
            $reached = $stage !== null && $case->hasReachedStage($stage);

            if (! $reached) {
                $unusedPercent += (float) $allocation->percentage;
            }

            $breakdown[] = [
                'stage' => $allocation->stage,
                'percentage' => (float) $allocation->percentage,
                'reached' => $reached,
            ];
        }

        $refundable = round($paid * ($unusedPercent / 100), 2);

        return [
            $refundable,
            $paid - $refundable,
            'A first draft had been delivered but the wording was not approved. The portion '
            .'of the fee allocated to stages not yet performed has been refunded.',
            ['unused_percent' => $unusedPercent, 'allocations' => $breakdown],
        ];
    }

    /** @return array<int, array{stage: string, label: string, occurred_at: string}> */
    private function stagesReached(LegalCase $case): array
    {
        return $case->stageTimestamps
            ->sortBy(fn ($t) => $t->stage->order())
            ->map(fn ($t) => [
                'stage' => $t->stage->value,
                'label' => $t->stage->label(),
                'occurred_at' => $t->occurred_at->toIso8601String(),
            ])
            ->values()
            ->all();
    }

    private function allocationFor(string $stage, float $paid): float
    {
        $percentage = (float) (FeeAllocation::where('stage', $stage)->value('percentage') ?? 0);

        return round($paid * ($percentage / 100), 2);
    }
}
