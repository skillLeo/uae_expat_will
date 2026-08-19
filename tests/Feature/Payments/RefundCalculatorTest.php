<?php

use App\Domain\Cases\Enums\CaseStage;
use App\Domain\Cases\Enums\CaseStatus;
use App\Domain\Cases\Enums\InternalStatus;
use App\Domain\Payments\Enums\PaymentStatus;
use App\Domain\Payments\Enums\RefundBand;
use App\Domain\Payments\Services\RefundCalculator;
use App\Models\FeeAllocation;
use App\Models\LegalCase;
use App\Models\Payment;
use Database\Seeders\FeeAllocationSeeder;

beforeEach(function () {
    seedPlatform();
    $this->seed(FeeAllocationSeeder::class);

    $this->case = LegalCase::create([
        'reference' => 'SLC-2026-00020',
        'status' => CaseStatus::LegalReviewAndDrafting,
        'internal_status' => InternalStatus::Drafting,
        'service_type' => 'standard_will',
    ]);

    $this->payment = Payment::create([
        'case_id' => $this->case->id,
        'gateway' => 'telr',
        'amount' => 2199.00,
        'vat_amount' => 109.95,
        'total_amount' => 2308.95,
        'status' => PaymentStatus::Paid,
        'paid_at' => now(),
    ]);

    $this->calculator = app(RefundCalculator::class);
});

function reachStage(CaseStage $stage): void
{
    test()->case->stageTimestamps()->create([
        'stage' => $stage,
        'occurred_at' => now(),
    ]);
    test()->case->refresh()->load('stageTimestamps');
}

it('gives band A and a full refund when nothing substantive has started', function () {
    reachStage(CaseStage::Payment);

    $result = $this->calculator->calculate($this->payment->fresh());

    expect($result['band'])->toBe(RefundBand::A)
        ->and($result['refundable'])->toBe(2308.95)
        ->and($result['deduction'])->toBe(0.0);
});

it('gives band B once substantive work has started', function () {
    reachStage(CaseStage::Payment);
    reachStage(CaseStage::SubstantiveWorkStarted);

    $result = $this->calculator->calculate($this->payment->fresh());

    expect($result['band'])->toBe(RefundBand::B)
        ->and($result['deduction'])->toBeGreaterThan(0)
        ->and($result['refundable'])->toBeLessThan(2308.95);
});

it('honours a documented deduction in band B', function () {
    reachStage(CaseStage::SubstantiveWorkStarted);

    $result = $this->calculator->calculate($this->payment->fresh(), documentedDeduction: 500.00);

    expect($result['deduction'])->toBe(500.0)
        ->and($result['refundable'])->toBe(1808.95)
        ->and($result['calculation']['workings']['deduction_source'])->toBe('documented');
});

it('gives band C and refunds the unused portion once a draft is delivered', function () {
    reachStage(CaseStage::Payment);
    reachStage(CaseStage::SubstantiveWorkStarted);
    reachStage(CaseStage::FirstDraftDelivered);

    $result = $this->calculator->calculate($this->payment->fresh());

    expect($result['band'])->toBe(RefundBand::C)
        ->and($result['refundable'])->toBeGreaterThan(0)
        ->and($result['refundable'])->toBeLessThan(2308.95)
        // The working is stored so the figure can be justified a year later.
        ->and($result['calculation']['workings'])->toHaveKey('unused_percent');
});

it('gives band D and no refund at each of the three final triggers', function (string $stage) {
    reachStage(CaseStage::Payment);
    reachStage(CaseStage::from($stage));

    $result = $this->calculator->calculate($this->payment->fresh());

    expect($result['band'])->toBe(RefundBand::D)
        ->and($result['refundable'])->toBe(0.0);
})->with(['final_approval', 'third_party_committed', 'authority_submitted']);

it('takes the furthest stage reached, not the last one recorded', function () {
    // Stage rows can be written out of order. The band must still be the
    // furthest point reached, never whichever row happened to arrive last.
    reachStage(CaseStage::AuthoritySubmitted);
    reachStage(CaseStage::SubstantiveWorkStarted);

    expect($this->calculator->calculate($this->payment->fresh())['band'])->toBe(RefundBand::D);
});

it('derives the band only from stage timestamps, not from case status', function () {
    $this->case->update(['internal_status' => InternalStatus::RegisteredAndDelivered]);
    reachStage(CaseStage::Payment);

    // Status says the matter is finished; the stage timestamps say no
    // substantive work was done. The timestamps are the evidence.
    expect($this->calculator->calculate($this->payment->fresh())['band'])->toBe(RefundBand::A);
});

it('stores the full working on the calculation', function () {
    reachStage(CaseStage::Payment);
    reachStage(CaseStage::FirstDraftDelivered);

    $calc = $this->calculator->calculate($this->payment->fresh())['calculation'];

    expect($calc)->toHaveKeys(['band', 'band_description', 'total_paid', 'stages_reached', 'refundable', 'deduction', 'calculated_at'])
        ->and($calc['stages_reached'])->toHaveCount(2);
});

it('degrades safely in band C when no fee allocation is configured', function () {
    FeeAllocation::query()->delete();
    reachStage(CaseStage::FirstDraftDelivered);

    $result = $this->calculator->calculate($this->payment->fresh());

    expect($result['band'])->toBe(RefundBand::C)
        ->and($result['calculation']['workings'])->toHaveKey('warning');
});
