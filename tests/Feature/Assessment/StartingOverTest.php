<?php

/**
 * Finishing an assessment must not end the site for that browser.
 *
 * Ahmed reported option four giving "no contact and also difc final result".
 * The cause was not option four. He had completed a DIFC assessment earlier,
 * and every later visit to /assessment was redirected straight back to that
 * old result — so whatever he picked at question one was discarded and he was
 * shown the previous outcome instead.
 */

use App\Models\Assessment;

beforeEach(function () {
    seedPlatform();
    seedQuestionnaire();
});

function finishAnAssessment(string $service = 'difc'): void
{
    test()->get('/assessment');

    foreach (cleanAnswers(['q1' => $service]) as $k => $v) {
        test()->post('/assessment/answer', ['question_key' => $k, 'value' => $v]);

        if ($k === 'q2') {
            test()->post('/assessment/contact', [
                'contact_name' => 'Aisha Rahman',
                'contact_email' => 'aisha@example.com',
                'contact_phone' => '+971500000000',
            ]);
        }
    }

    test()->post('/assessment/submit', [
        'declarations' => Assessment::first()->version->declarations->pluck('id')->all(),
    ]);
}

it('lets someone who has finished start again from the homepage', function () {
    finishAnAssessment('difc');

    // The homepage hero navigates to /assessment carrying question one.
    $this->get('/assessment?q1=review_existing&q2=yes')
        ->assertRedirect('/specialist-request/existing_will_service');
});

it('does not show the previous result to somebody choosing a new service', function () {
    finishAnAssessment('difc');

    $this->get('/assessment?q1=estate_death&q2=yes')
        ->assertRedirect('/specialist-request/estate_administration');
});

it('lets someone mid-assessment change their mind from the homepage', function () {
    // Not finished — just part way through, and back on the homepage picking
    // something else. The new choice has to win.
    $this->get('/assessment?q1=review_existing&q2=yes')
        ->assertRedirect('/specialist-request/existing_will_service');

    $this->get('/assessment?q1=estate_death&q2=yes')
        ->assertRedirect('/specialist-request/estate_administration');
});

it('starts a fresh assessment rather than rewriting a submitted one', function () {
    finishAnAssessment('new_will');
    $submitted = Assessment::first();

    $this->post('/assessment/answer', ['question_key' => 'q1', 'value' => 'review_existing']);

    // The completed one is untouched — it is the record a case was created from.
    expect($submitted->fresh()->load('answers')->answerSet()->get('q1'))->toBe('new_will')
        ->and(Assessment::count())->toBe(2);
});

it('still shows the result to somebody who simply refreshes', function () {
    finishAnAssessment('new_will');

    // No question one, so this is not somebody starting over.
    $this->get('/assessment')->assertRedirect('/assessment/result');
});

it('offers a way to begin again from the result screen', function () {
    finishAnAssessment('new_will');

    $this->post('/assessment/restart')->assertRedirect('/assessment');
    $this->get('/assessment')->assertInertia(fn ($p) => $p
        ->component('Assessment/Question')
        ->where('question.key', 'q1'));
});

it('keeps the completed assessment as a record after a restart', function () {
    finishAnAssessment('new_will');
    $this->post('/assessment/restart');

    expect(Assessment::whereNotNull('completed_at')->count())->toBe(1);
});

it('sends an estate enquiry from the homepage to the request form', function () {
    // The homepage hero used to intercept "someone has died" and show a panel
    // of its own with a link to /contact. It never reached the request form,
    // which is why the client kept reporting no contact form for it while
    // every test against /assessment passed.
    $this->get('/assessment?q1=estate_death')
        ->assertRedirect('/specialist-request/estate_administration');
});

it('sends an existing-Will enquiry from the homepage to the request form', function () {
    $this->get('/assessment?q1=review_existing')
        ->assertRedirect('/specialist-request/existing_will_service');
});

it('does not record a blank age for an enquiry that never reaches question two', function () {
    $this->get('/assessment?q1=estate_death');

    expect(Assessment::first()->load('answers')->answerSet()->get('q2'))->toBeNull();
});
