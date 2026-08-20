<?php

namespace App\Domain\Payments\Actions;

use App\Domain\Cases\Actions\RecordStageTimestamp;
use App\Domain\Cases\Enums\CaseStage;
use App\Domain\Payments\Enums\PaymentStatus;
use App\Domain\Payments\Enums\PaymentType;
use App\Models\LegalCase;
use App\Models\Payment;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Records a bank transfer or cash payment taken outside the gateway.
 *
 * It writes the stage timestamp exactly as a gateway payment does, so the
 * refund engine treats both identically. A refund must not depend on how the
 * money happened to arrive.
 *
 * Which stage gets written depends on what the payment is FOR. Summit's own fee
 * marks `payment`, the stage the refund bands are measured from. An authority
 * charge marks `third_party_committed` instead — the stage band D turns on —
 * because that is precisely what has happened: money has gone to a third party
 * and is not coming back.
 */
class RecordManualPayment
{
    public function __construct(private RecordStageTimestamp $recordStage) {}

    public function execute(
        LegalCase $case,
        float $amount,
        string $method,
        string $stageLabel,
        ?string $reference = null,
        PaymentType $type = PaymentType::ProfessionalFee,
    ): Payment {
        return DB::transaction(function () use ($case, $amount, $method, $stageLabel, $reference, $type) {
            // Read per type, so the treatment of a pass-through charge can be
            // changed in one place once Summit's accountant has ruled on it.
            $vat = round($amount * $type->vatRate() / 100, 2);

            $payment = Payment::create([
                'case_id' => $case->id,
                'gateway' => 'manual',
                'gateway_reference' => $reference,
                'amount' => $amount,
                'vat_amount' => $vat,
                'total_amount' => round($amount + $vat, 2),
                'currency' => setting('commercial.currency', 'AED'),
                'type' => $type,
                'stage_label' => $stageLabel,
                'status' => PaymentStatus::Paid,
                'method' => $method,
                'paid_at' => now(),
                'created_by' => Auth::guard('admin')->id(),
            ]);

            $payment->events()->create([
                'type' => 'manual_payment_recorded',
                'source' => 'manual_record',
                'payload' => ['method' => $method, 'reference' => $reference],
                'occurred_at' => now(),
            ]);

            if ($type->isDisbursement()) {
                // Kept out of paid_amount, which is measured against the
                // professional fee quote. Adding an authority charge to it made
                // the client dashboard read as an overpayment.
                $this->recordStage->execute($case, CaseStage::ThirdPartyCommitted);
            } else {
                $case->increment('paid_amount', $payment->total_amount);
                $this->recordStage->execute($case, CaseStage::Payment);
            }

            activity('payments')
                ->performedOn($payment)
                ->causedBy(Auth::guard('admin')->user())
                ->withProperties([
                    'case' => $case->reference,
                    'method' => $method,
                    'amount' => $amount,
                    'type' => $type->value,
                ])
                ->log('Manual payment recorded');

            return $payment;
        });
    }
}
