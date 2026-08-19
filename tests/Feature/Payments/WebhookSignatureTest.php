<?php

use App\Domain\Payments\Drivers\TelrDriver;
use App\Domain\Settings\Services\SettingsRepository;

beforeEach(function () {
    seedPlatform();
    $this->settings = app(SettingsRepository::class);
    $this->settings->set('payment.webhook_secret', 'whsec_test_123');
    $this->driver = app(TelrDriver::class);
});

function sign(string $body, string $secret = 'whsec_test_123'): array
{
    return ['x-telr-signature' => [hash_hmac('sha256', $body, $secret)]];
}

it('accepts a correctly signed payload', function () {
    $body = json_encode(['order' => ['ref' => 'ABC123', 'status' => ['text' => 'Paid']]]);

    expect($this->driver->verifyWebhookSignature($body, sign($body)))->toBeTrue();
});

it('rejects a tampered payload', function () {
    $body = json_encode(['order' => ['ref' => 'ABC123', 'status' => ['text' => 'Paid']]]);
    $headers = sign($body);

    $tampered = json_encode(['order' => ['ref' => 'ABC123', 'status' => ['text' => 'Refunded']]]);

    expect($this->driver->verifyWebhookSignature($tampered, $headers))->toBeFalse();
});

it('rejects a payload signed with the wrong secret', function () {
    $body = 'payload';

    expect($this->driver->verifyWebhookSignature($body, sign($body, 'wrong-secret')))->toBeFalse();
});

it('rejects a payload with no signature header at all', function () {
    expect($this->driver->verifyWebhookSignature('payload', []))->toBeFalse();
});

it('rejects everything when no secret is configured', function () {
    $this->settings->set('payment.webhook_secret', null);
    $body = 'payload';

    // A missing setting must never accidentally open the endpoint to anyone.
    expect(app(TelrDriver::class)->verifyWebhookSignature($body, sign($body)))->toBeFalse();
});

it('maps gateway statuses onto our own vocabulary', function (string $gateway, ?string $expected) {
    $parsed = $this->driver->parseWebhook(['order' => ['ref' => 'X', 'status' => ['text' => $gateway]]]);

    expect($parsed['status'])->toBe($expected);
})->with([
    ['Paid', 'paid'],
    ['Authorised', 'paid'],
    ['Pending', 'pending'],
    ['Cancelled', 'cancelled'],
    ['Declined', 'failed'],
    ['Expired', 'failed'],
    ['Refunded', 'refunded'],
    ['Something unexpected', null],
]);

it('extracts the gateway reference from a webhook', function () {
    $parsed = $this->driver->parseWebhook(['order' => ['ref' => 'REF-999', 'status' => ['text' => 'Paid']]]);

    expect($parsed['reference'])->toBe('REF-999');
});
