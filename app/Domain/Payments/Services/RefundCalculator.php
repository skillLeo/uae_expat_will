<?php

namespace App\Domain\Payments\Services;

use App\Domain\Cases\Enums\CaseStage;
use App\Domain\Payments\Enums\RefundBand;
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
 */
class RefundCalculator
{
    /**
     * @return array{
     *     band: RefundBand,
     *     refundable: float,
     *     deduction: float,
     *     reason: string,
     *     calculation: array<string, mixed>
     * }
     */
    public function calculate(Payment $payment, ?float $documentedDeduction = null): array
    {
        $case = $payment->legalCase;
        $paid = (float) $payment->total_amount;
        $band = $this->band($case);

        $reached = $case->stageTimestamps
            ->sortBy(fn ($t) => $t->stage->order())
            ->map(fn ($t) => [
                'stage' => $t->stage->value,
                'label' => $t->stage->label(),
                'occurred_at' => $t->occurred_at->toIso8601String(),
            ])
            ->values()
            ->all();

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
                'total_paid' => $paid,
                'stages_reached' => $reached,
                'refundable' => round($refundable, 2),
                'deduction' => round($deduction, 2),
                'workings' => $workings,
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

    private function allocationFor(string $stage, float $paid): float
    {
        $percentage = (float) (FeeAllocation::where('stage', $stage)->value('percentage') ?? 0);

        return round($paid * ($percentage / 100), 2);
    }
}
