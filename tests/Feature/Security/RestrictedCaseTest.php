<?php

use App\Domain\Cases\Enums\CaseStatus;
use App\Domain\Cases\Enums\InternalStatus;
use App\Models\Customer;
use App\Models\LegalCase;
use App\Models\Scopes\RestrictedCaseScope;

beforeEach(function () {
    seedPlatform();

    $this->customer = Customer::create([
        'full_name' => 'Test Customer',
        'email' => 'customer@example.com',
    ]);

    $this->restricted = tap(new LegalCase([
        'reference' => 'SLC-2026-00001',
        'customer_id' => $this->customer->id,
        'status' => CaseStatus::UnderReview,
        'internal_status' => InternalStatus::HeldCapacityOrInfluence,
        'is_restricted' => true,
    ]), function (LegalCase $case) {
        $case->setRestrictedReason('Capacity concern indicated at Q15B.');
        $case->save();
    });

    $this->ordinary = LegalCase::create([
        'reference' => 'SLC-2026-00002',
        'customer_id' => $this->customer->id,
        'status' => CaseStatus::UnderReview,
        'internal_status' => InternalStatus::HeldSensitiveMatter,
        'is_restricted' => false,
    ]);
});

it('never selects the restricted column for a user without the permission', function () {
    $this->actingAs(adminUser(['Case Handler']), 'admin');

    $case = LegalCase::find($this->restricted->id);

    // The requirement is specific: the response must not contain the field AT
    // ALL, not merely hide it. So it must be absent from the attributes.
    expect(array_key_exists('restricted_reason_encrypted', $case->getAttributes()))->toBeFalse()
        ->and($case->restrictedReason())->toBeNull();
});

it('selects the restricted column for a user with the permission', function () {
    $this->actingAs(adminUser(['Legal Reviewer']), 'admin');

    $case = LegalCase::find($this->restricted->id);

    expect($case->restrictedReason())->toBe('Capacity concern indicated at Q15B.');
});

it('keeps a restricted case present and countable, never invisible', function () {
    $this->actingAs(adminUser(['Case Handler']), 'admin');

    // A case that silently vanishes is a different bug — a coordinator would
    // chase a matter that appears to have disappeared.
    expect(LegalCase::count())->toBe(2)
        ->and(LegalCase::pluck('reference'))->toContain('SLC-2026-00001');
});

it('never leaks the restricted reason through serialisation', function () {
    $this->actingAs(adminUser(['Legal Reviewer']), 'admin');

    $json = LegalCase::find($this->restricted->id)->toJson();

    expect($json)->not->toContain('restricted_reason_encrypted')
        ->and($json)->not->toContain('Capacity concern');
});

it('never matches a search against restricted content', function () {
    $this->actingAs(adminUser(['Legal Reviewer']), 'admin');

    // Searching the reason text must find nothing — a search that matches on
    // hidden content leaks that content by revealing which query found it.
    expect(LegalCase::search('Capacity concern')->count())->toBe(0)
        ->and(LegalCase::search('SLC-2026-00001')->count())->toBe(1);
});

it('scopes a case handler to their assigned cases only', function () {
    $handler = adminUser(['Case Handler']);
    $this->restricted->update(['assigned_to' => $handler->id]);
    $this->actingAs($handler, 'admin');

    expect(LegalCase::visibleTo($handler)->pluck('reference')->all())
        ->toBe(['SLC-2026-00001']);
});

it('shows every case to a user with cases.view.all', function () {
    $reviewer = adminUser(['Legal Reviewer']);
    $this->actingAs($reviewer, 'admin');

    expect(LegalCase::visibleTo($reviewer)->count())->toBe(2);
});

it('reports the viewer permission correctly', function () {
    $this->actingAs(adminUser(['Case Handler']), 'admin');
    expect(RestrictedCaseScope::viewerMaySeeRestricted())->toBeFalse();

    $this->actingAs(adminUser(['Legal Reviewer']), 'admin');
    expect(RestrictedCaseScope::viewerMaySeeRestricted())->toBeTrue();
});

it('never offers payment on a restricted case', function () {
    expect($this->restricted->allowsPayment())->toBeFalse();
});

it('maps every internal status to exactly one customer-facing group', function () {
    foreach (InternalStatus::cases() as $status) {
        expect($status->group())->toBeInstanceOf(CaseStatus::class);
    }

    // Only capacity or influence restricts a case.
    $restricting = array_filter(InternalStatus::cases(), fn ($s) => $s->restrictsCase());
    expect($restricting)->toHaveCount(1)
        ->and(reset($restricting))->toBe(InternalStatus::HeldCapacityOrInfluence);
});
