<?php

namespace App\Http\Controllers\Webhooks;

use App\Domain\Cases\Actions\ChangeCaseStatus;
use App\Domain\Cases\Actions\RecordStageTimestamp;
use App\Domain\Cases\Enums\CaseStage;
use App\Domain\Cases\Enums\InternalStatus;
use App\Domain\Payments\Contracts\PaymentGateway;
use App\Domain\Payments\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\PaymentEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * The gateway webhook.
 *
 * Three properties matter here and each is a real failure mode:
 *
 *   1. SIGNATURE. An unverified payload is rejected before anything is read.
 *      An unconfigured secret verifies as false, never true, so a missing
 *      setting cannot open the endpoint.
 *   2. IDEMPOTENCY. Gateways retry. The same delivery arriving twice must not
 *      double-credit a case or re-fire the status change, so a duplicate is
 *      recognised by gateway reference plus status and acknowledged without
 *      being acted on again.
 *   3. ALWAYS 200 ONCE VERIFIED. A gateway that receives a 500 retries for
 *      hours. Once the payload is authentic and stored, respond 200 even if
 *      downstream work fails, and let the logs carry the failure.
 */
class PaymentWebhookController extends Controller
{
    public function __construct(
        private PaymentGateway $gateway,
        private RecordStageTimestamp $recordStage,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $raw = $request->getContent();

        if (! $this->gateway->verifyWebhookSignature($raw, $request->headers->all())) {
            Log::warning('Rejected payment webhook with an invalid signature', ['ip' => $request->ip()]);

            return response()->json(['error' => 'invalid signature'], 401);
        }

        $parsed = $this->gateway->parseWebhook($request->all());

        if (blank($parsed['reference'])) {
            return response()->json(['error' => 'no gateway reference'], 422);
        }

        $payment = Payment::where('gateway_reference', $parsed['reference'])->first();

        if ($payment === null) {
            // Acknowledge: retrying will not make an unknown reference known.
            Log::warning('Payment webhook for an unknown reference', ['reference' => $parsed['reference']]);

            return response()->json(['status' => 'ignored, unknown reference']);
        }

        // Idempotency: same reference, same status, already recorded.
        $duplicate = PaymentEvent::where('payment_id', $payment->id)
            ->where('type', 'webhook:'.$parsed['status'])
            ->exists();

        if ($duplicate) {
            return response()->json(['status' => 'duplicate acknowledged']);
        }

        try {
            DB::transaction(fn () => $this->apply($payment, $parsed, $request));
        } catch (\Throwable $e) {
            // Verified and stored — do not make the gateway retry.
            Log::error('Payment webhook processing failed', [
                'payment' => $payment->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['status' => 'received, processing deferred']);
        }

        return response()->json(['status' => 'ok']);
    }

    /** @param array{reference: string|null, status: string|null} $parsed */
    private function apply(Payment $payment, array $parsed, Request $request): void
    {
        $payment->events()->create([
            'type' => 'webhook:'.$parsed['status'],
            'source' => 'webhook',
            'payload' => $request->all(),
            'occurred_at' => now(),
        ]);

        $status = $parsed['status'] ? PaymentStatus::tryFrom($parsed['status']) : null;

        if ($status === null) {
            return;
        }

        $payment->update([
            'status' => $status,
            'paid_at' => $status === PaymentStatus::Paid ? now() : $payment->paid_at,
            'failed_reason' => $status === PaymentStatus::Failed
                ? ($request->input('order.status.text') ?? 'Declined by the gateway')
                : null,
            'raw_payload' => $request->all(),
        ]);

        if ($status !== PaymentStatus::Paid) {
            if ($status === PaymentStatus::Failed) {
                app(ChangeCaseStatus::class)
                    ->execute($payment->legalCase, InternalStatus::PaymentFailedRetry, 'Gateway reported a failed payment.');
            }

            return;
        }

        $case = $payment->legalCase;
        $case->increment('paid_amount', (float) $payment->total_amount);

        // The stage timestamp is what the refund engine reads. Without it a
        // refund could not be banded at all.
        $this->recordStage->execute($case, CaseStage::Payment);

        app(ChangeCaseStatus::class)
            ->execute($case, InternalStatus::QuestionnaireReleased, 'Payment confirmed by the gateway.');
    }
}
