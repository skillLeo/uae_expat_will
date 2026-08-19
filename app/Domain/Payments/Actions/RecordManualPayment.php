<?php

namespace App\Domain\Payments\Actions;

use App\Domain\Cases\Actions\RecordStageTimestamp;
use App\Domain\Cases\Enums\CaseStage;
use App\Domain\Payments\Enums\PaymentStatus;
use App\Models\LegalCase;
use App\Models\Payment;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Records a bank transfer or cash payment taken outside the gateway.
 *
 * It writes the payment stage timestamp exactly as a gateway payment does, so
 * the refund engine treats both identically. A refund must not depend on how
 * the money happened to arrive.
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
    ): Payment {
        return DB::transaction(function () use ($case, $amount, $method, $stageLabel, $reference) {
            $vatRate = (float) setting('commercial.vat_rate', 5);
            $vat = round($amount * $vatRate / 100, 2);

            $payment = Payment::create([
                'case_id' => $case->id,
                'gateway' => 'manual',
                'gateway_reference' => $reference,
                'amount' => $amount,
                'vat_amount' => $vat,
                'total_amount' => round($amount + $vat, 2),
                'currency' => setting('commercial.currency', 'AED'),
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

            $case->increment('paid_amount', $payment->total_amount);
            $this->recordStage->execute($case, CaseStage::Payment);

            activity('payments')
                ->performedOn($payment)
                ->causedBy(Auth::guard('admin')->user())
                ->withProperties(['case' => $case->reference, 'method' => $method, 'amount' => $amount])
                ->log('Manual payment recorded');

            return $payment;
        });
    }
}
