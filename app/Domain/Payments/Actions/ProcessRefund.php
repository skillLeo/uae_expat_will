<?php

namespace App\Domain\Payments\Actions;

use App\Domain\Payments\Enums\PaymentStatus;
use App\Domain\Payments\Services\RefundCalculator;
use App\Models\Payment;
use App\Models\Refund;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Issues a refund.
 *
 * The band and the amount come from RefundCalculator, which reads only the case
 * stage timestamps. The whole calculation is stored on the refund row so the
 * figure can still be explained to a client months later without anyone having
 * to reconstruct it.
 */
class ProcessRefund
{
    public function __construct(private RefundCalculator $calculator) {}

    public function execute(Payment $payment, ?float $documentedDeduction = null, ?string $reason = null): Refund
    {
        return DB::transaction(function () use ($payment, $documentedDeduction, $reason) {
            $result = $this->calculator->calculate($payment, $documentedDeduction);

            $refund = Refund::create([
                'payment_id' => $payment->id,
                'band' => $result['band'],
                'amount' => $result['refundable'],
                'deduction_amount' => $result['deduction'],
                'deduction_reason' => $reason ?? $result['reason'],
                'calculation' => $result['calculation'],
                'status' => 'completed',
                'processed_at' => now(),
                'processed_by' => Auth::guard('admin')->id(),
            ]);

            $payment->update(['status' => PaymentStatus::Refunded]);

            $payment->events()->create([
                'type' => 'refunded',
                'source' => 'manual_record',
                'payload' => ['band' => $result['band']->value, 'amount' => $result['refundable']],
                'occurred_at' => now(),
            ]);

            activity('payments')
                ->performedOn($refund)
                ->causedBy(Auth::guard('admin')->user())
                ->withProperties([
                    'case' => $payment->legalCase->reference,
                    'band' => $result['band']->value,
                    'refunded' => $result['refundable'],
                    'deducted' => $result['deduction'],
                ])
                ->log('Refund issued');

            return $refund;
        });
    }
}
