<?php

use App\Domain\Assessment\DTOs\AnswerSet;
use App\Domain\Assessment\RoutingEngine;
use App\Models\Questionnaire;

/**
 * DIFC Wills are open only to people who are not Muslim and never have been.
 * Somebody who asked for a DIFC Will at question one needs telling that before
 * they answer the religion question. Wording supplied by Summit, 26 Aug 2026.
 */
beforeEach(function () {
    seedPlatform();
    seedQuestionnaire();
    $this->get('/assessment');
});

function reachReligion(string $service): void
{
    test()->post('/assessment/answer', ['question_key' => 'q1', 'value' => $service]);
    test()->post('/assessment/answer', ['question_key' => 'q2', 'value' => 'yes']);
    test()->post('/assessment/contact', [
        'contact_name' => 'Aisha Rahman',
        'contact_email' => 'aisha@example.com',
        'contact_phone' => '+971500000000',
    ]);
    test()->post('/assessment/answer', ['question_key' => 'q3', 'value' => 'GB']);
    test()->post('/assessment/answer', ['question_key' => 'q4', 'value' => 'outside_uae']);
}

it('shows the eligibility notice on the religion question for a DIFC request', function () {
    reachReligion('difc');

    $this->get('/assessment')->assertInertia(fn ($p) => $p
        ->where('question.key', 'q5')
        ->where('notice.heading', 'DIFC eligibility notice')
        ->where('notice.body', fn ($b) => str_contains($b, 'not Muslim and have never been Muslim'))
        ->where('notice.conflict_options', ['muslim', 'previously_muslim'])
        ->where('notice.conflict_body', fn ($b) => str_contains($b, 'may not be available to you')
            && str_contains($b, 'No payment will be taken at this stage')));
});

it('shows nobody else the DIFC notice', function () {
    reachReligion('new_will');

    $this->get('/assessment')->assertInertia(fn ($p) => $p
        ->where('question.key', 'q5')
        ->where('notice', null));
});

it('does not attach the notice to any other question', function () {
    $this->post('/assessment/answer', ['question_key' => 'q1', 'value' => 'difc']);

    $this->get('/assessment')->assertInertia(fn ($p) => $p
        ->where('question.key', 'q2')
        ->where('notice', null));
});

it('still routes a DIFC request to review rather than payment', function () {
    // The notice is information. The rule is what protects the customer.
    $engine = new RoutingEngine(
        Questionnaire::screening()->publishedVersion()
    );

    $result = $engine->evaluate(AnswerSet::make(cleanAnswers(['q1' => 'difc'])));

    expect($result->allowsPayment())->toBeFalse();
});
