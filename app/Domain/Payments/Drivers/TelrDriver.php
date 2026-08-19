<?php

namespace App\Domain\Payments\Drivers;

use App\Domain\Payments\Contracts\PaymentGateway;
use App\Domain\Settings\Services\SettingsRepository;
use App\Models\Payment;
use Illuminate\Support\Facades\Http;

class TelrDriver implements PaymentGateway
{
    private const ENDPOINT = 'https://secure.telr.com/gateway/order.json';

    public function __construct(private SettingsRepository $settings) {}

    public function name(): string
    {
        return 'telr';
    }

    public function createPaymentLink(Payment $payment): array
    {
        $storeId = $this->settings->get('payment.store_id');
        $authKey = $this->settings->get('payment.auth_key');

        if (blank($storeId) || blank($authKey)) {
            return ['ok' => false, 'url' => null, 'reference' => null, 'error' => 'The payment gateway is not configured.'];
        }

        try {
            $response = Http::asForm()->timeout(20)->post(self::ENDPOINT, [
                'ivp_method' => 'create',
                'ivp_store' => $storeId,
                'ivp_authkey' => $authKey,
                'ivp_cart' => $payment->id,
                'ivp_test' => $this->settings->get('payment.test_mode', true) ? '1' : '0',
                'ivp_amount' => number_format((float) $payment->total_amount, 2, '.', ''),
                'ivp_currency' => $payment->currency,
                // Deliberately generic. A gateway description is visible on a bank
                // statement, so it must not describe the matter.
                'ivp_desc' => 'Professional fee — '.$payment->legalCase->reference,
                'return_auth' => url($this->settings->get('payment.return_url', '/payment/complete')),
                'return_can' => url($this->settings->get('payment.cancel_url', '/payment/cancelled')),
                'return_decl' => url($this->settings->get('payment.cancel_url', '/payment/cancelled')),
            ]);

            $json = $response->json();

            if (isset($json['error'])) {
                return ['ok' => false, 'url' => null, 'reference' => null, 'error' => $json['error']['note'] ?? 'Gateway error.'];
            }

            return [
                'ok' => true,
                'url' => $json['order']['url'] ?? null,
                'reference' => $json['order']['ref'] ?? null,
                'error' => null,
            ];
        } catch (\Throwable $e) {
            return ['ok' => false, 'url' => null, 'reference' => null, 'error' => $e->getMessage()];
        }
    }

    public function checkStatus(Payment $payment): array
    {
        if (blank($payment->gateway_reference)) {
            return ['ok' => false, 'status' => null, 'error' => 'This payment has no gateway reference.'];
        }

        try {
            $response = Http::asForm()->timeout(20)->post(self::ENDPOINT, [
                'ivp_method' => 'check',
                'ivp_store' => $this->settings->get('payment.store_id'),
                'ivp_authkey' => $this->settings->get('payment.auth_key'),
                'order_ref' => $payment->gateway_reference,
            ]);

            $status = $response->json('order.status.text');

            return ['ok' => $status !== null, 'status' => $this->mapStatus($status), 'error' => null];
        } catch (\Throwable $e) {
            return ['ok' => false, 'status' => null, 'error' => $e->getMessage()];
        }
    }

    /**
     * Verify the webhook signature.
     *
     * hash_equals is used rather than === so the comparison is not timing-
     * variable. An unconfigured secret returns FALSE — never true — so a missing
     * setting cannot accidentally open the endpoint to anyone.
     */
    public function verifyWebhookSignature(string $rawBody, array $headers): bool
    {
        $secret = $this->settings->get('payment.webhook_secret');

        if (blank($secret)) {
            return false;
        }

        $provided = $headers['x-telr-signature'][0]
            ?? $headers['X-Telr-Signature'][0]
            ?? '';

        if ($provided === '') {
            return false;
        }

        return hash_equals(hash_hmac('sha256', $rawBody, $secret), $provided);
    }

    public function parseWebhook(array $payload): array
    {
        return [
            'reference' => $payload['order']['ref'] ?? $payload['tran_ref'] ?? null,
            'status' => $this->mapStatus($payload['order']['status']['text'] ?? $payload['status'] ?? null),
        ];
    }

    private function mapStatus(?string $telrStatus): ?string
    {
        return match (strtolower((string) $telrStatus)) {
            'paid', 'authorised', 'authorized', 'captured' => 'paid',
            'pending', 'created' => 'pending',
            'cancelled', 'canceled' => 'cancelled',
            'declined', 'failed', 'expired' => 'failed',
            'refunded' => 'refunded',
            default => null,
        };
    }
}
