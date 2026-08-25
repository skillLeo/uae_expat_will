<?php

use App\Domain\Assessment\DTOs\AnswerSet;
use App\Domain\Assessment\DTOs\RoutingResult;
use App\Domain\Assessment\Enums\Outcome;
use App\Domain\Assessment\RoutingEngine;

beforeEach(function () {
    seedPlatform();
    $this->version = seedQuestionnaire();
    $this->engine = new RoutingEngine($this->version);
});

function evaluateAnswers(array $answers): RoutingResult
{
    return test()->engine->evaluate(AnswerSet::make($answers));
}

// ---------------------------------------------------------------- terminal

it('stops immediately when the enquiry is about an estate after death', function () {
    $result = evaluateAnswers(['q1' => 'estate_death']);

    expect($result->outcome)->toBe(Outcome::StopRefer)
        ->and($result->isTerminal())->toBeTrue()
        ->and($result->allowsPayment())->toBeFalse();
});

it('stops immediately when the person is under 18', function () {
    $result = evaluateAnswers(['q1' => 'new_will', 'q2' => 'no']);

    expect($result->outcome)->toBe(Outcome::StopIneligible)
        ->and($result->allowsPayment())->toBeFalse();
});

it('stops immediately for a UAE citizen', function () {
    $result = evaluateAnswers(['q1' => 'new_will', 'q2' => 'yes', 'q3' => 'AE']);

    expect($result->outcome)->toBe(Outcome::StopIneligible);
});

it('does not stop for any other nationality', function () {
    expect(evaluateAnswers(cleanAnswers(['q3' => 'IN']))->outcome)->toBe(Outcome::Continue_);
});

// ------------------------------------------------- the cross-question rule

it('flags a Muslim customer whose distribution needs the wider route, without stopping them', function (string $wish) {
    // Summit still gets told. The customer is no longer held: per the approved
    // handoff the enhanced review is an internal classification and "must not
    // remove the payment option from an ADJD or Dubai Courts candidate".
    $result = evaluateAnswers(cleanAnswers(['q5' => 'muslim', 'q13a' => [$wish]]));

    expect($result->outcome)->toBe(Outcome::ContinueFlag)
        ->and($result->flags)->toContain('enhanced_review')
        ->and($result->allowsPayment())->toBeTrue();
})->with(['specific_gift', 'different_percentages', 'gift_to_friend']);

it('lets a non-Muslim customer continue with a route mark for the same wish', function (string $wish) {
    $result = evaluateAnswers(cleanAnswers(['q5' => 'non_muslim', 'q13a' => [$wish]]));

    expect($result->outcome)->toBe(Outcome::ContinueRouteMark)
        ->and($result->routeMarks)->toContain('wider_dubai_route')
        ->and($result->allowsPayment())->toBeTrue();
})->with(['specific_gift', 'different_percentages', 'gift_to_friend']);

it('applies the same religion split to an unmarried partner inheriting', function () {
    $muslim = evaluateAnswers(cleanAnswers(['q5' => 'muslim', 'q6' => 'unmarried_partner', 'q6a' => 'yes_no_competing']));
    $nonMuslim = evaluateAnswers(cleanAnswers(['q5' => 'non_muslim', 'q6' => 'unmarried_partner', 'q6a' => 'yes_no_competing']));

    // The split still exists and is still recorded — it just no longer decides
    // whether the customer may pay.
    expect($muslim->outcome)->toBe(Outcome::ContinueFlag)
        ->and($muslim->flags)->toContain('enhanced_review')
        ->and($nonMuslim->outcome)->toBe(Outcome::ContinueRouteMark)
        ->and($muslim->allowsPayment())->toBeTrue();
});

it('does not fire the cross-question rule when the religion answer is absent', function () {
    $answers = cleanAnswers(['q13a' => ['different_percentages']]);
    unset($answers['q5']);

    // With no religion answer neither branch of R-12 can match, so the case
    // simply continues rather than defaulting to the stricter outcome.
    expect(evaluateAnswers($answers)->outcome)->toBe(Outcome::Continue_);
});

// ------------------------------------------------------------- precedence

it('records an enhanced review without taking the payment option away', function () {
    $result = evaluateAnswers(cleanAnswers(['q12' => ['foreign_will']]));

    expect($result->outcome)->toBe(Outcome::ContinueFlag)
        ->and($result->flags)->toContain('enhanced_review')
        ->and($result->allowsPayment())->toBeTrue();
});

it('still keeps a DIFC request away from the standard checkout', function () {
    // The one review that survives. A DIFC Will needs its own quotation, so it
    // must never reach the standard payment screen.
    $result = evaluateAnswers(cleanAnswers(['q1' => 'difc']));

    expect($result->outcome)->toBe(Outcome::Review)
        ->and($result->allowsPayment())->toBeFalse();
});

it('sends an amendment to an existing Will straight to the team', function () {
    $result = evaluateAnswers(cleanAnswers(['q1' => 'review_existing']));

    expect($result->outcome)->toBe(Outcome::StopRefer)
        ->and($result->allowsPayment())->toBeFalse();
});

it('lets urgent review beat everything that would otherwise continue', function () {
    $result = evaluateAnswers(cleanAnswers([
        'q12' => ['foreign_will'],
        'q15b' => 'feel_pressured',
    ]));

    expect($result->outcome)->toBe(Outcome::UrgentReview)
        ->and($result->isRestricted())->toBeTrue();
});

it('lets a terminal stop beat everything else', function () {
    $result = evaluateAnswers(cleanAnswers([
        'q2' => 'no',
        'q12' => ['foreign_will'],
        'q15b' => 'feel_pressured',
    ]));

    expect($result->outcome)->toBe(Outcome::StopIneligible);
});

it('marks every capacity or influence answer as urgent and restricted', function (string $answer) {
    $result = evaluateAnswers(cleanAnswers(['q15b' => $answer]));

    expect($result->outcome)->toBe(Outcome::UrgentReview)
        ->and($result->isRestricted())->toBeTrue()
        ->and($result->allowsPayment())->toBeFalse();
})->with(['health_condition', 'someone_helping', 'feel_pressured', 'no_or_unsure']);

it('does not restrict a case merely because an answer was sensitive', function () {
    // Regression: sensitivity is not restriction. Marking every sensitive answer
    // restricted would hide ordinary cases from the coordinators who work them.
    $result = evaluateAnswers(cleanAnswers(['q5' => 'muslim', 'q13a' => ['different_percentages']]));

    expect($result->isRestricted())->toBeFalse();
});

it('does not restrict the one remaining held outcome either', function () {
    $result = evaluateAnswers(cleanAnswers(['q1' => 'difc']));

    expect($result->outcome)->toBe(Outcome::Review)
        ->and($result->isRestricted())->toBeFalse();
});

it('flags the fourth religion option rather than holding it', function () {
    $result = evaluateAnswers(cleanAnswers(['q5' => 'prefer_not_to_say']));

    expect($result->outcome)->toBe(Outcome::ContinueFlag)
        ->and($result->flags)->toContain('enhanced_review');
});

// ----------------------------------------------------- flags and reminders

it('accumulates flags, reminders and route marks', function () {
    $result = evaluateAnswers(cleanAnswers([
        'q14' => 'names_later',
        'q16' => 'arabic',
        'q11' => ['joint_clear'],
    ]));

    expect($result->allowsPayment())->toBeTrue()
        ->and($result->reminders)->toContain('executor_names')
        ->and($result->flags)->toContain('arabic_assistance')
        ->and($result->flags)->toContain('joint_ownership');
});

it('itemises a trigger reason per question', function () {
    $result = evaluateAnswers(cleanAnswers(['q5' => 'muslim', 'q13a' => ['different_percentages']]));
    $keys = collect($result->triggerReasonsArray())->pluck('question_key');

    expect($keys)->toContain('q13a')->toContain('q5');
});

it('redacts restricted trigger reasons for an unauthorised viewer', function () {
    $result = evaluateAnswers(cleanAnswers(['q15b' => 'feel_pressured']));
    $redacted = $result->triggerReasonsArray(includeRestricted: false);

    foreach ($redacted as $reason) {
        expect($reason['question_key'])->toBeNull()
            ->and($reason['answer_label'])->toBe('Restricted — authorised legal staff only');
    }

    // The COUNT is still safe to show — it leaks nothing about the content.
    expect($result->triggerCount())->toBeGreaterThan(0);
});

// ---------------------------------------------------- conditional visibility

it('shows and hides conditional questions from the answers', function (array $answers, string $key, bool $expected) {
    $visible = $this->engine->visibleQuestions(AnswerSet::make($answers))->pluck('key');

    expect($visible->contains($key))->toBe($expected);
})->with([
    'emirate shown in the UAE' => [['q1' => 'new_will', 'q2' => 'yes', 'q3' => 'GB', 'q4' => 'in_uae'], 'q4a', true],
    'emirate hidden outside' => [['q1' => 'new_will', 'q2' => 'yes', 'q3' => 'GB', 'q4' => 'outside_uae'], 'q4a', false],
    'partner question shown' => [['q6' => 'unmarried_partner'], 'q6a', true],
    'partner question hidden' => [['q6' => 'married_first'], 'q6a', false],
    'crypto question shown' => [['q9' => 'uae_only', 'q10' => ['crypto']], 'q10b', true],
    'crypto question hidden' => [['q9' => 'uae_only', 'q10' => ['bank']], 'q10b', false],
]);

it('skips the whole UAE assets section for a guardianship-only matter', function () {
    $visible = $this->engine
        ->visibleQuestions(AnswerSet::make(cleanAnswers(['q9' => 'guardianship_only'])))
        ->pluck('key');

    expect($visible)->not->toContain('q10')
        ->and($visible)->not->toContain('q10b')
        ->and($visible)->not->toContain('q11');
});

it('ignores a rule whose question is not currently visible', function () {
    // q6a would route to review, but q6 says married — so q6a is hidden and its
    // stale answer must not be allowed to route the case.
    $result = evaluateAnswers(cleanAnswers(['q6' => 'married_first', 'q6a' => 'not_sure']));

    expect($result->outcome)->toBe(Outcome::Continue_);
});

// --------------------------------------------------------------- progress

it('never emits a question count in the progress payload', function () {
    $progress = $this->engine->progress(AnswerSet::make(['q1' => 'new_will']))->toArray();

    expect($progress)->toHaveKeys(['current_stage_key', 'current_stage_label', 'stages', 'percent'])
        ->and($progress)->not->toHaveKey('total')
        ->and($progress)->not->toHaveKey('answered')
        ->and(json_encode($progress))->not->toMatch('/\b\d+\s+of\s+\d+\b/');
});
