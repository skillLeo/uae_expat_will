<?php

/**
 * Stage nine of the journey says Summit explains the authority's charge before
 * the customer authorises it, and refund band D turns on a committed
 * third-party cost. These tests hold that path together.
 *
 * They started life as a record of what was broken. Findings AF-01 to AF-04 and
 * AF-06 are now closed, and each test below is the thing that keeps them closed.
 * AF-05 — whether VAT applies to a pass-through government charge — is a tax
 * question for Summit's accountant, so the behaviour is deliberately unchanged
 * and the test says so out loud.
 */

use App\Domain\Cases\Actions\RecordStageTimestamp;
use App\Domain\Cases\Enums\CaseStage;
use App\Domain\Cases\Enums\CaseStatus;
use App\Domain\Cases\Enums\InternalStatus;
use App\Domain\Payments\Actions\ProcessRefund;
use App\Domain\Payments\Actions\RecordManualPayment;
use App\Domain\Payments\Enums\PaymentStatus;
use App\Domain\Payments\Enums\PaymentType;
use App\Domain\Payments\Enums\RefundBand;
use App\Domain\Payments\Exceptions\DisbursementNeedsDocumentedRefund;
use App\Domain\Payments\Services\RefundCalculator;
use App\Domain\Settings\Services\SettingsRepository;
use App\Models\Customer;
use App\Models\LegalCase;
use App\Models\Payment;
use App\Models\User;
use Database\Seeders\FeeAllocationSeeder;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    seedPlatform();
    $this->seed(FeeAllocationSeeder::class);
    app(SettingsRepository::class)->set('payment.webhook_secret', 'whsec_test_123');

    $this->case = LegalCase::create([
        'reference' => 'SLC-2026-00090',
        'status' => CaseStatus::LegalReviewAndDrafting,
        'internal_status' => InternalStatus::Drafting,
        'service_type' => 'standard_will',
        'quoted_amount' => 2199.00,
    ]);

    $this->record = app(RecordManualPayment::class);
});

/** Raises a manual payment of the given type against the case. */
function raise(float $amount, PaymentType $type, string $label = 'Professional fee'): Payment
{
    return test()->record->execute(
        test()->case->fresh(), $amount, 'bank_transfer', $label, null, $type,
    );
}

/** Posts a signed gateway callback for a payment. */
function confirmAtGateway(string $reference, string $text = 'Paid')
{
    $body = json_encode(['order' => ['ref' => $reference, 'status' => ['text' => $text]]]);

    return test()->call('POST', '/webhooks/payment', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_TELR_SIGNATURE' => hash_hmac('sha256', $body, 'whsec_test_123'),
    ], $body);
}

function pendingGatewayPayment(string $ref, PaymentType $type, float $total = 787.50): Payment
{
    return Payment::create([
        'case_id' => test()->case->id,
        'gateway' => 'telr',
        'gateway_reference' => $ref,
        'amount' => round($total / 1.05, 2),
        'vat_amount' => round($total - ($total / 1.05), 2),
        'total_amount' => $total,
        'type' => $type,
        'stage_label' => $type->isDisbursement() ? 'DIFC registration fee' : 'Professional fee',
        'status' => PaymentStatus::Pending,
    ]);
}

// ═══════════════════════════════════════════════════ AF-01 — typed payments

it('records what every payment is for', function () {
    expect(Schema::hasColumn('payments', 'type'))->toBeTrue();

    $fee = raise(2199.00, PaymentType::ProfessionalFee);
    $charge = raise(750.00, PaymentType::Disbursement, 'DIFC registration fee');

    expect($fee->type)->toBe(PaymentType::ProfessionalFee)
        ->and($charge->type)->toBe(PaymentType::Disbursement)
        ->and($charge->isDisbursement())->toBeTrue();
});

it('treats an untyped payment as the professional fee', function () {
    // Every payment raised before the column existed was Summit's own fee —
    // the platform only ever raised one per case.
    expect((new Payment)->type)->toBe(PaymentType::ProfessionalFee);
});

it('separates the two kinds in a query', function () {
    raise(2199.00, PaymentType::ProfessionalFee);
    raise(750.00, PaymentType::Disbursement, 'DIFC registration fee');

    expect(Payment::professionalFees()->count())->toBe(1)
        ->and(Payment::disbursements()->count())->toBe(1);
});

it('makes the case screen offer a choice rather than a text box', function () {
    $user = adminUser(['Super Administrator']);
    $this->actingAs($user, 'admin')->withSession(['2fa.passed' => true]);

    // A type that is not one of the two is refused, so no free text can leak
    // back in through a crafted request.
    $this->post("/admin/cases/{$this->case->id}/manual-payment", [
        'amount' => 750, 'method' => 'bank_transfer', 'type' => 'whatever',
    ])->assertSessionHasErrors('type');

    // And an authority charge has to say which authority.
    $this->post("/admin/cases/{$this->case->id}/manual-payment", [
        'amount' => 750, 'method' => 'bank_transfer', 'type' => 'disbursement',
    ])->assertSessionHasErrors('stage_label');
});

it('labels a professional fee for the operator', function () {
    $user = adminUser(['Super Administrator']);
    $this->actingAs($user, 'admin')->withSession(['2fa.passed' => true]);

    $this->post("/admin/cases/{$this->case->id}/manual-payment", [
        'amount' => 2199, 'method' => 'bank_transfer', 'type' => 'professional_fee',
    ])->assertSessionHasNoErrors();

    expect(Payment::first()->stage_label)->toBe('Professional fee');
});

// ══════════════════════════════════════════════ AF-02 — refunds by document

it('refuses to band an authority charge', function () {
    $charge = raise(750.00, PaymentType::Disbursement, 'DIFC registration fee');
    app(RecordStageTimestamp::class)->execute($this->case, CaseStage::FirstDraftDelivered);

    // Band C would have refunded "the portion allocated to stages not yet
    // performed" — money that may already be with the authority.
    expect(fn () => app(RefundCalculator::class)->calculate($charge->fresh()->load('legalCase.stageTimestamps')))
        ->toThrow(DisbursementNeedsDocumentedRefund::class);
});

it('refunds an authority charge on a documented figure and reason', function () {
    $charge = raise(750.00, PaymentType::Disbursement, 'DIFC registration fee');

    $result = app(RefundCalculator::class)->calculate(
        $charge->fresh()->load('legalCase.stageTimestamps'),
        500.00,
        'Registration was never lodged; the Wills Service Centre confirmed 500 is returnable.',
    );

    expect($result['band'])->toBeNull()
        ->and($result['refundable'])->toBe(500.00)
        ->and($result['deduction'])->toBe(287.50)
        ->and($result['calculation']['workings']['source'])->toBe('documented')
        ->and($result['calculation']['workings']['documented_reason'])->toContain('never lodged')
        ->and($result['calculation']['payment_type'])->toBe('disbursement');
});

it('never refunds more than was actually paid', function () {
    $charge = raise(750.00, PaymentType::Disbursement, 'DIFC registration fee');

    $result = app(RefundCalculator::class)->calculate(
        $charge->fresh()->load('legalCase.stageTimestamps'), 9999.00, 'Typed in error.',
    );

    expect($result['refundable'])->toBe(787.50)
        ->and($result['calculation']['workings']['capped_at_amount_paid'])->toBeTrue();
});

it('stores the working on a disbursement refund exactly as it does for a fee', function () {
    $charge = raise(750.00, PaymentType::Disbursement, 'DIFC registration fee');

    $refund = app(ProcessRefund::class)->execute($charge->fresh(), 500.00, 'Not lodged, fee returnable.');

    expect($refund->band)->toBeNull()
        ->and((float) $refund->amount)->toBe(500.00)
        ->and($refund->calculation['calculated_at'])->not->toBeEmpty()
        ->and($refund->calculation['stage_label'])->toBe('DIFC registration fee')
        ->and($refund->deduction_reason)->toBe('Not lodged, fee returnable.');
});

it('leaves the four professional-fee bands exactly as they were', function () {
    $fee = raise(2199.00, PaymentType::ProfessionalFee);

    // Band A: nothing beyond payment.
    expect(app(RefundCalculator::class)->calculate($fee->fresh()->load('legalCase.stageTimestamps'))['band'])
        ->toBe(RefundBand::A);

    app(RecordStageTimestamp::class)->execute($this->case, CaseStage::SubstantiveWorkStarted);
    expect(app(RefundCalculator::class)->calculate($fee->fresh()->load('legalCase.stageTimestamps'))['band'])
        ->toBe(RefundBand::B);

    app(RecordStageTimestamp::class)->execute($this->case, CaseStage::FirstDraftDelivered);
    expect(app(RefundCalculator::class)->calculate($fee->fresh()->load('legalCase.stageTimestamps'))['band'])
        ->toBe(RefundBand::C);

    app(RecordStageTimestamp::class)->execute($this->case, CaseStage::FinalApproval);
    expect(app(RefundCalculator::class)->calculate($fee->fresh()->load('legalCase.stageTimestamps'))['band'])
        ->toBe(RefundBand::D);
});

// ═══════════════════════════════════════════ AF-03 — the stage records itself

it('commits the third-party cost when an authority charge is recorded', function () {
    raise(750.00, PaymentType::Disbursement, 'DIFC registration fee');

    expect($this->case->fresh()->load('stageTimestamps')->hasReachedStage(CaseStage::ThirdPartyCommitted))
        ->toBeTrue();
});

it('puts a case into band D once the authority has been paid', function () {
    $fee = raise(2199.00, PaymentType::ProfessionalFee);
    raise(750.00, PaymentType::Disbursement, 'DIFC registration fee');

    // No button pressed by anybody. Paying the authority IS the commitment.
    expect(app(RefundCalculator::class)->band($this->case->fresh()->load('stageTimestamps')))
        ->toBe(RefundBand::D)
        ->and(app(RefundCalculator::class)->calculate($fee->fresh()->load('legalCase.stageTimestamps'))['refundable'])
        ->toBe(0.0);
});

it('commits the third-party cost on the gateway path too', function () {
    $charge = pendingGatewayPayment('AUTH-1', PaymentType::Disbursement);

    confirmAtGateway('AUTH-1')->assertOk();

    expect($charge->fresh()->status)->toBe(PaymentStatus::Paid)
        ->and($this->case->fresh()->load('stageTimestamps')->hasReachedStage(CaseStage::ThirdPartyCommitted))
        ->toBeTrue();
});

it('does not re-date a stage that is already set', function () {
    app(RecordStageTimestamp::class)->execute($this->case, CaseStage::ThirdPartyCommitted, now()->subDays(3));
    $original = $this->case->fresh()->load('stageTimestamps')->stageAt(CaseStage::ThirdPartyCommitted)->occurred_at;

    raise(750.00, PaymentType::Disbursement, 'A second authority charge');

    $after = $this->case->fresh()->load('stageTimestamps')->stageAt(CaseStage::ThirdPartyCommitted);

    expect($this->case->fresh()->stageTimestamps->where('stage', CaseStage::ThirdPartyCommitted)->count())->toBe(1)
        ->and($after->occurred_at->timestamp)->toBe($original->timestamp);
});

it('does not mark the professional fee paid when only an authority charge arrives', function () {
    // Otherwise a disbursement would look like the fee to the refund engine.
    raise(750.00, PaymentType::Disbursement, 'DIFC registration fee');

    expect($this->case->fresh()->load('stageTimestamps')->hasReachedStage(CaseStage::Payment))->toBeFalse();
});

// ═════════════════════════════════════════ AF-04 — no walking the case back

it('leaves the case status alone on a second confirmed payment', function () {
    // The matter is ready for submission. Taking the authority's charge must
    // not drag it back to "questionnaire released" in front of the customer.
    raise(2199.00, PaymentType::ProfessionalFee);
    $this->case->update(['internal_status' => InternalStatus::SubmissionPrepared]);

    pendingGatewayPayment('AUTH-1', PaymentType::Disbursement);
    confirmAtGateway('AUTH-1')->assertOk();

    expect($this->case->fresh()->internal_status)->toBe(InternalStatus::SubmissionPrepared);
});

it('still opens the questionnaire on the first payment', function () {
    $this->case->update(['internal_status' => InternalStatus::PaymentLinkSent]);
    pendingGatewayPayment('FEE-1', PaymentType::ProfessionalFee, 2308.95);

    confirmAtGateway('FEE-1')->assertOk();

    expect($this->case->fresh()->internal_status)->toBe(InternalStatus::QuestionnaireReleased);
});

it('does not advance a second professional fee either', function () {
    // A balance or amendment fee is still not the first payment.
    raise(1000.00, PaymentType::ProfessionalFee);
    $this->case->update(['internal_status' => InternalStatus::InternalQa]);

    pendingGatewayPayment('FEE-2', PaymentType::ProfessionalFee, 1199.00);
    confirmAtGateway('FEE-2')->assertOk();

    expect($this->case->fresh()->internal_status)->toBe(InternalStatus::InternalQa);
});

it('keeps webhook idempotency intact', function () {
    $this->case->update(['internal_status' => InternalStatus::PaymentLinkSent]);
    pendingGatewayPayment('FEE-1', PaymentType::ProfessionalFee, 2308.95);

    confirmAtGateway('FEE-1')->assertOk();
    $afterFirst = (float) $this->case->fresh()->paid_amount;

    // The gateway retries. It must not credit the case twice.
    confirmAtGateway('FEE-1')->assertOk()->assertJson(['status' => 'duplicate acknowledged']);

    expect((float) $this->case->fresh()->paid_amount)->toBe($afterFirst);
});

// ══════════════════════════════════════════════ AF-06 — the totals separate

it('keeps an authority charge out of the professional fee total', function () {
    raise(2199.00, PaymentType::ProfessionalFee);
    raise(750.00, PaymentType::Disbursement, 'DIFC registration fee');

    // 2,308.95 paid against 2,199 quoted — fully paid, not overpaid.
    expect((float) $this->case->fresh()->paid_amount)->toBe(2308.95)
        ->and((float) $this->case->quoted_amount)->toBe(2199.00);
});

it('keeps it out of the total on the gateway path as well', function () {
    raise(2199.00, PaymentType::ProfessionalFee);
    pendingGatewayPayment('AUTH-1', PaymentType::Disbursement);

    confirmAtGateway('AUTH-1')->assertOk();

    expect((float) $this->case->fresh()->paid_amount)->toBe(2308.95);
});

it('shows the customer what each amount was for', function () {
    // The client area is gated; open it for this test only. The gate itself is
    // untouched.
    app(SettingsRepository::class)->set('features.client_portal_enabled', true);

    $user = User::factory()->create(['user_type' => User::TYPE_CUSTOMER]);
    $customer = Customer::create([
        'user_id' => $user->id, 'full_name' => 'Test Customer', 'email' => $user->email,
    ]);
    $this->case->update(['customer_id' => $customer->id]);

    raise(2199.00, PaymentType::ProfessionalFee);
    raise(750.00, PaymentType::Disbursement, 'DIFC Wills Service Centre registration fee');

    $this->actingAs($user)
        ->get('/client')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('cases.0.payments_made', 2)
            // Two lines, each saying what it was for — not one total that
            // reads as an overpayment against the 2,199 quote.
            ->where('cases.0.payments_made.0.what', 'Professional fee')
            ->where('cases.0.payments_made.0.is_disbursement', false)
            ->where('cases.0.payments_made.1.what', 'DIFC Wills Service Centre registration fee')
            ->where('cases.0.payments_made.1.is_disbursement', true)
            ->where('cases.0.paid_amount', 2308.95));
});

// ══════════════════════════════════════════════════ AF-05 — deliberately open

it('still charges VAT on a pass-through authority charge', function () {
    // Unchanged on purpose. Whether VAT applies to a government charge Summit
    // merely collects is a question for their accountant. When the answer
    // arrives, PaymentType::vatRate() is the only thing that changes.
    $charge = raise(750.00, PaymentType::Disbursement, 'DIFC registration fee');

    expect((float) $charge->vat_amount)->toBe(37.50)
        ->and((float) $charge->total_amount)->toBe(787.50)
        ->and(PaymentType::Disbursement->vatRate())->toBe(5.0);
});

it('reads the rate from settings rather than a constant', function () {
    app(SettingsRepository::class)->set('commercial.vat_rate', 0);

    $charge = raise(750.00, PaymentType::Disbursement, 'DIFC registration fee');

    expect((float) $charge->vat_amount)->toBe(0.0)
        ->and((float) $charge->total_amount)->toBe(750.00);
});
