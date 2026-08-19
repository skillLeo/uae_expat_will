<?php

namespace App\Domain\Payments\Contracts;

use App\Models\Payment;

/**
 * A payment gateway.
 *
 * Adding a second provider is a new class implementing this interface plus a
 * settings value — never a rewrite. No implementation may ever receive, log or
 * store raw card data: checkout is hosted by the gateway, and this application
 * only ever sees a link and a webhook.
 */
interface PaymentGateway
{
    /**
     * @return array{ok: bool, url: string|null, reference: string|null, error: string|null}
     */
    public function createPaymentLink(Payment $payment): array;

    /**
     * @return array{ok: bool, status: string|null, error: string|null}
     */
    public function checkStatus(Payment $payment): array;

    /** Verify an inbound webhook's signature. Never trust an unverified payload. */
    public function verifyWebhookSignature(string $rawBody, array $headers): bool;

    /** @return array{reference: string|null, status: string|null} */
    public function parseWebhook(array $payload): array;

    public function name(): string;
}
