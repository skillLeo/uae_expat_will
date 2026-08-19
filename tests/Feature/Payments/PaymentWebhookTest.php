<?php

use App\Domain\Cases\Enums\CaseStage;
use App\Domain\Cases\Enums\CaseStatus;
use App\Domain\Cases\Enums\InternalStatus;
use App\Domain\Payments\Enums\PaymentStatus;
use App\Domain\Settings\Services\SettingsRepository;
use App\Models\LegalCase;
use App\Models\Payment;
use App\Models\PaymentEvent;

beforeEach(function () {
    seedPlatform();
    app(SettingsRepository::class)->set('payment.webhook_secret', 'whsec_test_123');

    $this->case = LegalCase::create([
        'reference' => 'SLC-2026-08001',
        'status' => CaseStatus::AcceptedPaymentRequired,
        'internal_status' => InternalStatus::PaymentLinkSent,
        'service_type' => 'standard_will',
    ]);

    $this->payment = Payment::create([
        'case_id' => $this->case->id,
        'gateway' => 'telr',
        'gateway_reference' => 'REF-12345',
        'amount' => 2199, 'vat_amount' => 109.95, 'total_amount' => 2308.95,
        'status' => PaymentStatus::Pending,
    ]);
});

/** Posts a signed webhook exactly as the gateway would. */
function postWebhook(array $payload, ?string $secret = 'whsec_test_123')
{
    $body = json_encode($payload);

    return test()->call(
        'POST',
        '/webhooks/payment',
        [],
        [],
        [],
        [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_TELR_SIGNATURE' => hash_hmac('sha256', $body, $secret ?? 'wrong'),
        ],
        $body,
    );
}

function paidPayload(string $ref = 'REF-12345'): array
{
    return ['order' => ['ref' => $ref, 'status' => ['text' => 'Paid']]];
}

it('rejects a payload with a bad signature', function () {
    postWebhook(paidPayload(), secret: 'the-wrong-secret')->assertStatus(401);

    expect($this->payment->fresh()->status)->toBe(PaymentStatus::Pending)
        ->and(PaymentEvent::count())->toBe(0);
});

it('rejects a payload whose body was tampered with after signing', function () {
    $body = json_encode(paidPayload());
    $signature = hash_hmac('sha256', $body, 'whsec_test_123');

    // Same signature, different body.
    $tampered = json_encode(['order' => ['ref' => 'REF-12345', 'status' => ['text' => 'Refunded']]]);

    $this->call('POST', '/webhooks/payment', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_TELR_SIGNATURE' => $signature,
    ], $tampered)->assertStatus(401);

    expect($this->payment->fresh()->status)->toBe(PaymentStatus::Pending);
});

it('rejects everything when no secret is configured', function () {
    app(SettingsRepository::class)->set('payment.webhook_secret', null);

    postWebhook(paidPayload())->assertStatus(401);
});

it('marks the payment paid on a valid webhook', function () {
    postWebhook(paidPayload())->assertOk();

    $payment = $this->payment->fresh();

    expect($payment->status)->toBe(PaymentStatus::Paid)
        ->and($payment->paid_at)->not->toBeNull();
});

it('records the payment stage so the refund engine can band it', function () {
    postWebhook(paidPayload())->assertOk();

    $case = $this->case->fresh()->load('stageTimestamps');

    expect($case->hasReachedStage(CaseStage::Payment))->toBeTrue();
});

it('advances the case status and credits the amount', function () {
    postWebhook(paidPayload())->assertOk();

    $case = $this->case->fresh();

    expect($case->internal_status)->toBe(InternalStatus::QuestionnaireReleased)
        ->and((float) $case->paid_amount)->toBe(2308.95);
});

it('is idempotent when the same delivery is replayed', function () {
    postWebhook(paidPayload())->assertOk();
    $afterFirst = $this->case->fresh()->paid_amount;

    // Gateways retry. A replay must not double-credit or re-fire anything.
    postWebhook(paidPayload())->assertOk();
    postWebhook(paidPayload())->assertOk();

    expect($this->case->fresh()->paid_amount)->toBe($afterFirst)
        ->and(PaymentEvent::where('payment_id', $this->payment->id)
            ->where('type', 'webhook:paid')->count())->toBe(1);
});

it('writes a payment event for every accepted delivery', function () {
    postWebhook(paidPayload())->assertOk();

    $event = PaymentEvent::where('payment_id', $this->payment->id)->first();

    expect($event->source)->toBe('webhook')
        ->and($event->type)->toBe('webhook:paid')
        ->and($event->payload)->not->toBeNull();
});

it('moves a failed payment to the retry status', function () {
    postWebhook(['order' => ['ref' => 'REF-12345', 'status' => ['text' => 'Declined']]])->assertOk();

    expect($this->payment->fresh()->status)->toBe(PaymentStatus::Failed)
        ->and($this->case->fresh()->internal_status)->toBe(InternalStatus::PaymentFailedRetry);
});

it('acknowledges an unknown reference rather than making the gateway retry', function () {
    $response = postWebhook(paidPayload('REF-DOES-NOT-EXIST'));

    $response->assertOk();
    expect($response->json('status'))->toContain('unknown reference');
});

it('is throttled', function () {
    $route = collect(app('router')->getRoutes())->first(fn ($r) => $r->uri() === 'webhooks/payment');

    expect($route->gatherMiddleware())->toContain('throttle:webhooks');
});

it('is exempt from CSRF so the gateway can post without a session', function () {
    // Proven by the fact the posts above succeed at all — a CSRF failure would
    // be a 419 long before the signature check ran.
    postWebhook(paidPayload())->assertOk();
});
