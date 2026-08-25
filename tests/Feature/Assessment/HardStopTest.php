<?php

/**
 * A stop has to actually stop.
 *
 * Both of these were raised by Summit after testing the live site: somebody who
 * says they are under 18 must not be able to carry on, and the UAE must not be
 * offered as a nationality only for the person to be turned away a screen later.
 */

use App\Models\Assessment;

beforeEach(function () {
    seedPlatform();
    seedQuestionnaire();
    $this->get('/assessment');
});

function answerWith(string $key, mixed $value)
{
    return test()->post('/assessment/answer', ['question_key' => $key, 'value' => $value]);
}

it('ends the journey the moment someone says they are under 18', function () {
    answerWith('q1', 'new_will');
    answerWith('q2', 'no');

    $this->get('/assessment')->assertInertia(fn ($p) => $p
        ->component('Assessment/Terminal')
        ->where('outcome', 'stop_ineligible'));
});

it('refuses any further answer once a stop has fired', function () {
    answerWith('q1', 'new_will');
    answerWith('q2', 'no');

    // The interface shows the terminal screen, but a crafted request must not
    // be able to walk past it towards a payment screen.
    answerWith('q3', 'GB');
    answerWith('q5', 'non_muslim');

    $assessment = Assessment::first()->load('answers');

    expect($assessment->answerSet()->get('q3'))->toBeNull()
        ->and($assessment->answerSet()->get('q5'))->toBeNull();

    $this->get('/assessment')->assertInertia(fn ($p) => $p->component('Assessment/Terminal'));
});

it('does not offer the UAE as a nationality', function () {
    answerWith('q1', 'new_will');
    answerWith('q2', 'yes');
    $this->post('/assessment/contact', [
        'contact_name' => 'Aisha Rahman',
        'contact_email' => 'aisha@example.com',
        'contact_phone' => '+971 50 123 4567',
    ]);

    $this->get('/assessment')->assertInertia(fn ($p) => $p
        ->component('Assessment/Question')
        ->where('question.key', 'q3')
        ->where('countries', fn ($c) => ! array_key_exists('AE', (array) $c))
        // The rest of the world is still there.
        ->where('countries', fn ($c) => ($c['GB'] ?? null) === 'United Kingdom'));
});

it('still stops a UAE citizen who gets the value in anyway', function () {
    // Removing the option is a courtesy, not the control. The rule stays.
    answerWith('q1', 'new_will');
    answerWith('q2', 'yes');
    $this->post('/assessment/contact', [
        'contact_name' => 'Aisha Rahman',
        'contact_email' => 'aisha@example.com',
        'contact_phone' => '+971 50 123 4567',
    ]);
    answerWith('q3', 'AE');

    $this->get('/assessment')->assertInertia(fn ($p) => $p
        ->component('Assessment/Terminal')
        ->where('outcome', 'stop_ineligible'));
});
