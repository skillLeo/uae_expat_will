<?php

use App\Domain\Assessment\DTOs\AnswerSet;
use App\Domain\Assessment\RoutingEngine;
use App\Domain\Assessment\Services\AnswerValidator;
use App\Domain\Cases\Enums\CaseStage;
use App\Domain\Cases\Enums\CaseStatus;
use App\Domain\Cases\Enums\InternalStatus;
use App\Domain\Settings\Services\SettingsRepository;
use App\Models\Customer;
use App\Models\LegalCase;
use App\Models\Question;
use App\Models\Questionnaire;
use App\Models\QuestionnaireVersion;
use App\Models\User;
use Database\Seeders\DetailedQuestionnaireSeeder;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    seedPlatform();
    $this->seed(DetailedQuestionnaireSeeder::class);

    // The client area is gated; open it for these tests.
    app(SettingsRepository::class)->set('features.client_portal_enabled', true);

    $this->user = User::factory()->create(['user_type' => User::TYPE_CUSTOMER]);

    $customer = Customer::create([
        'user_id' => $this->user->id,
        'full_name' => 'Test Customer',
        'email' => $this->user->email,
    ]);

    $this->case = LegalCase::create([
        'reference' => 'SLC-2026-07001',
        'customer_id' => $customer->id,
        'status' => CaseStatus::QuestionnaireInProgress,
        'internal_status' => InternalStatus::QuestionnaireReleased,
        'service_type' => 'standard_will',
    ]);
});

function detailedVersion(): QuestionnaireVersion
{
    return Questionnaire::where('key', 'detailed')->firstOrFail()->publishedVersion();
}

it('publishes a detailed questionnaire so the client area has something to open', function () {
    $version = detailedVersion();

    expect($version)->not->toBeNull()
        ->and($version->questions()->count())->toBeGreaterThan(20)
        ->and($version->declarations()->count())->toBeGreaterThan(0)
        ->and($version->resultScreens()->count())->toBeGreaterThan(0);
});

it('refuses to open before the fee is paid', function () {
    // "Do not open the detailed questionnaire before acceptance, engagement
    // and payment" — the specification is explicit about this.
    $this->actingAs($this->user, 'web')
        ->get("/client/cases/{$this->case->id}/questionnaire")
        ->assertForbidden();
});

it('opens once the payment stage is recorded', function () {
    $this->case->stageTimestamps()->create(['stage' => CaseStage::Payment, 'occurred_at' => now()]);

    $this->actingAs($this->user, 'web')
        ->get("/client/cases/{$this->case->id}/questionnaire")
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Client/Questionnaire/Question'));
});

it('refuses another customer matter outright', function () {
    $this->case->stageTimestamps()->create(['stage' => CaseStage::Payment, 'occurred_at' => now()]);

    $stranger = User::factory()->create(['user_type' => User::TYPE_CUSTOMER]);

    $this->actingAs($stranger, 'web')
        ->get("/client/cases/{$this->case->id}/questionnaire")
        ->assertForbidden();
});

// ----------------------------------------------------- percentage validation

it('rejects a distribution that does not total 100', function (string $input, string $expected) {
    $question = Question::where('key', 'd_percentages')->firstOrFail();

    expect(fn () => app(AnswerValidator::class)->validate($question, $input))
        ->toThrow(ValidationException::class, $expected);
})->with([
    'under' => ['Alex 40% Jordan 30%', 'total 70%'],
    'over' => ['Alex 60% Jordan 50%', 'total 110%'],
    'no percentages at all' => ['Alex gets the house', 'percentage'],
]);

it('accepts a distribution that totals exactly 100', function (string $input) {
    $question = Question::where('key', 'd_percentages')->firstOrFail();

    expect(app(AnswerValidator::class)->validate($question, $input))->toBe($input);
})->with([
    'two equal shares' => ['Alex 50% Jordan 50%'],
    'three uneven shares' => ['Alex 50% Jordan 30% Sam 20%'],
    'decimal shares' => ['Alex 33.33% Jordan 33.33% Sam 33.34%'],
]);

it('leaves questions without the rule alone', function () {
    $question = Question::where('key', 'd_beneficiaries')->firstOrFail();

    // The rule is declared per question, so this one is untouched.
    expect(app(AnswerValidator::class)->validate($question, 'Alex Whitfield, my spouse'))
        ->toBe('Alex Whitfield, my spouse');
});

// ------------------------------------------------------ conditional sections

it('opens the guardian section only for a minor child', function () {
    $engine = new RoutingEngine(detailedVersion());

    $withMinor = $engine->visibleQuestions(AnswerSet::make([
        'd_has_children' => 'yes', 'd_minor_children' => 'yes',
    ]))->pluck('key');

    $withoutMinor = $engine->visibleQuestions(AnswerSet::make([
        'd_has_children' => 'yes', 'd_minor_children' => 'no',
    ]))->pluck('key');

    expect($withMinor)->toContain('d_guardian_permanent')
        ->and($withoutMinor)->not->toContain('d_guardian_permanent');
});

it('opens the spouse section only when a spouse or partner is named', function () {
    $engine = new RoutingEngine(detailedVersion());

    expect($engine->visibleQuestions(AnswerSet::make(['d_has_spouse' => 'spouse']))->pluck('key'))
        ->toContain('d_spouse_name')
        ->and($engine->visibleQuestions(AnswerSet::make(['d_has_spouse' => 'no']))->pluck('key'))
        ->not->toContain('d_spouse_name');
});

it('flags a foreign connection for the reviewer', function () {
    $engine = new RoutingEngine(detailedVersion());

    $result = $engine->evaluate(AnswerSet::make(['d_second_nationality' => 'yes']));

    expect($result->flags)->toContain('foreign_law_connection')
        // Nothing here routes to a stop: the customer has already been
        // accepted and paid, so there is nothing left to triage them into.
        ->and($result->outcome->allowsPayment())->toBeTrue();
});

it('treats almost every answer as sensitive', function () {
    $insensitive = detailedVersion()->questions()->where('is_sensitive', false)->pluck('key');

    // Names, family, beneficiaries and asset detail are all encrypted at rest.
    expect($insensitive->count())->toBeLessThanOrEqual(1);
});
