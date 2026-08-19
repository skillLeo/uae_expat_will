<?php

namespace App\Domain\Payments\Actions;

use App\Domain\Payments\Contracts\PaymentGateway;
use App\Domain\Payments\Enums\PaymentStatus;
use App\Models\LegalCase;
use App\Models\Payment;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Generates a payment link through the gateway API.
 *
 * Manual pasting of a link is not the normal workflow — the link is created
 * here, tied to the customer, the case, the amount and the stage, so a payment
 * can always be traced back to what it was for.
 */
class CreatePaymentLink
{
    public function __construct(private PaymentGateway $gateway) {}

    public function execute(LegalCase $case, float $amount, string $stageLabel): Payment
    {
        // A held matter must never be asked for money. This is the same rule the
        // UI enforces by not rendering the control, restated where it cannot be
        // bypassed by a crafted request.
        if (! $case->allowsPayment()) {
            throw new RuntimeException('This matter is held for review. No payment may be requested.');
        }

        return DB::transaction(function () use ($case, $amount, $stageLabel) {
            $vatRate = (float) setting('commercial.vat_rate', 5);
            $vat = round($amount * $vatRate / 100, 2);

            $payment = Payment::create([
                'case_id' => $case->id,
                'gateway' => $this->gateway->name(),
                'amount' => $amount,
                'vat_amount' => $vat,
                'total_amount' => round($amount + $vat, 2),
                'currency' => setting('commercial.currency', 'AED'),
                'stage_label' => $stageLabel,
                'status' => PaymentStatus::Pending,
                'link_token' => bin2hex(random_bytes(24)),
                'created_by' => Auth::guard('admin')->id(),
            ]);

            $result = $this->gateway->createPaymentLink($payment);

            if (! $result['ok']) {
                throw new RuntimeException($result['error'] ?? 'The gateway did not return a link.');
            }

            $payment->update([
                'link_url' => $result['url'],
                'gateway_reference' => $result['reference'],
            ]);

            $payment->events()->create([
                'type' => 'link_created',
                'source' => 'manual_record',
                'payload' => ['stage' => $stageLabel, 'amount' => $amount],
                'occurred_at' => now(),
            ]);

            activity('payments')
                ->performedOn($payment)
                ->causedBy(Auth::guard('admin')->user())
                ->withProperties(['case' => $case->reference, 'amount' => $amount])
                ->log('Payment link generated');

            return $payment->fresh();
        });
    }
}
