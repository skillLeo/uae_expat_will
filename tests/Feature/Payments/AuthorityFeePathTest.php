<?php

/**
 * Stage nine of the journey says Summit explains the authority's charge before
 * the customer authorises it, and refund band D references a committed
 * third-party cost. These tests trace that path against the code as it stands.
 *
 * Several of them assert behaviour that is wrong for an authority fee. They are
 * written that way deliberately: the brief asked for the gaps to be reported,
 * not closed, so each one below is the evidence behind a line in the readiness
 * report and the failing anchor for whoever is asked to close it.
 */

use App\Domain\Cases\Actions\RecordStageTimestamp;
use App\Domain\Cases\Enums\CaseStage;
use App\Domain\Cases\Enums\CaseStatus;
use App\Domain\Cases\Enums\InternalStatus;
use App\Domain\Payments\Actions\RecordManualPayment;
use App\Domain\Payments\Enums\PaymentStatus;
use App\Domain\Payments\Enums\RefundBand;
use App\Domain\Payments\Services\RefundCalculator;
use App\Domain\Settings\Services\SettingsRepository;
use App\Models\LegalCase;
use App\Models\Payment;
use Database\Seeders\FeeAllocationSeeder;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    seedPlatform();
    $this->seed(FeeAllocationSeeder::class);

    $this->case = LegalCase::create([
        'reference' => 'SLC-2026-00090',
        'status' => CaseStatus::LegalReviewAndDrafting,
        'internal_status' => InternalStatus::Drafting,
        'service_type' => 'standard_will',
        'quoted_amount' => 2199.00,
    ]);

    $this->record = app(RecordManualPayment::class);
});

// ------------------------------------------------------------------ it works

it('lets a second payment be raised against the same case', function () {
    // The one part of the path that is genuinely complete.
    $this->record->execute($this->case, 2199.00, 'bank_transfer', 'Professional fee');
    $this->record->execute($this->case->fresh(), 750.00, 'bank_transfer', 'DIFC registration fee');

    expect($this->case->fresh()->payments)->toHaveCount(2);
});

// ------------------------------------------------------------------- the gaps

it('has no column that says what a payment is for', function () {
    // stage_label is a free-text box an operator types into, defaulted to
    // "Professional fee" in the UI. Nothing constrains it, so no query, report
    // or rule can reliably separate the two kinds of money.
    expect(Schema::hasColumn('payments', 'type'))->toBeFalse()
        ->and(Schema::hasColumn('payments', 'purpose'))->toBeFalse()
        ->and(Schema::hasColumn('payments', 'is_disbursement'))->toBeFalse();
});

it('charges Summit VAT on a pass-through authority charge', function () {
    // An authority's charge is a disbursement, not Summit's supply. Both
    // payment actions apply commercial.vat_rate unconditionally, so raising a
    // 750 government fee bills the customer 787.50.
    $fee = $this->record->execute($this->case, 750.00, 'bank_transfer', 'DIFC registration fee');

    expect((float) $fee->vat_amount)->toBe(37.50)
        ->and((float) $fee->total_amount)->toBe(787.50);
});

it('gives an authority fee no stage timestamp of its own', function () {
    $this->record->execute($this->case, 2199.00, 'bank_transfer', 'Professional fee');
    $this->record->execute($this->case->fresh(), 750.00, 'bank_transfer', 'DIFC registration fee');

    $stages = $this->case->fresh()->stageTimestamps->pluck('stage');

    // Both writes land on the same `payment` stage, and third_party_committed —
    // the stage band D actually turns on — is never reached by paying the fee.
    expect($stages)->toHaveCount(1)
        ->and($stages->first())->toBe(CaseStage::Payment)
        ->and($this->case->fresh()->hasReachedStage(CaseStage::ThirdPartyCommitted))->toBeFalse();
});

it('only reaches band D if somebody remembers to press the stage button', function () {
    // The stage exists and is recordable by hand on the case screen. Nothing
    // in the payment path records it, so the link between "the authority has
    // been paid" and "band D applies" is a human remembering.
    app(RecordStageTimestamp::class)->execute($this->case, CaseStage::ThirdPartyCommitted);

    $payment = $this->record->execute($this->case->fresh(), 2199.00, 'bank_transfer', 'Professional fee');

    expect(app(RefundCalculator::class)->band($this->case->fresh()->load('stageTimestamps')))
        ->toBe(RefundBand::D);
});

it('applies the professional-fee refund bands to an authority fee', function () {
    $fee = $this->record->execute($this->case, 750.00, 'bank_transfer', 'DIFC registration fee');
    app(RecordStageTimestamp::class)->execute($this->case, CaseStage::FirstDraftDelivered);

    $result = app(RefundCalculator::class)->calculate($fee->fresh()->load('legalCase.stageTimestamps'));

    // Band C refunds "the portion of the fee allocated to stages not yet
    // performed" — a sentence about Summit's own fee, applied here to money
    // that may already be with the authority and unrecoverable.
    expect($result['band'])->toBe(RefundBand::C)
        ->and($result['reason'])->toContain('first draft had been delivered')
        ->and($result['refundable'])->toBeGreaterThan(0.0);
});

it('adds an authority fee to the same paid total as the professional fee', function () {
    $this->record->execute($this->case, 2199.00, 'bank_transfer', 'Professional fee');
    $this->record->execute($this->case->fresh(), 750.00, 'bank_transfer', 'DIFC registration fee');

    // The client dashboard shows quoted 2,199 against paid 3,096.75, which
    // reads as an overpayment rather than as two separate charges.
    expect((float) $this->case->fresh()->paid_amount)->toBe(3096.45)
        ->and((float) $this->case->quoted_amount)->toBe(2199.00);
});

it('sends the case backwards when a second gateway payment is confirmed', function () {
    // The webhook treats every confirmed payment as the first one and moves the
    // case to "questionnaire released". On an authority fee at stage nine that
    // is a regression of the customer-visible status.
    $this->case->update(['internal_status' => InternalStatus::SubmissionPrepared]);

    $fee = Payment::create([
        'case_id' => $this->case->id,
        'gateway' => 'telr',
        'gateway_reference' => 'AUTH-1',
        'amount' => 750.00, 'vat_amount' => 37.50, 'total_amount' => 787.50,
        'stage_label' => 'DIFC registration fee',
        'status' => PaymentStatus::Pending,
    ]);

    app(SettingsRepository::class)->set('payment.webhook_secret', 'whsec_test_123');

    $body = json_encode(['order' => ['ref' => 'AUTH-1', 'status' => ['text' => 'Paid']]]);

    $this->call('POST', '/webhooks/payment', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_TELR_SIGNATURE' => hash_hmac('sha256', $body, 'whsec_test_123'),
    ], $body)->assertOk();

    expect($fee->fresh()->status)->toBe(PaymentStatus::Paid)
        ->and($this->case->fresh()->internal_status)->toBe(InternalStatus::QuestionnaireReleased);
});
