<?php

/**
 * Contact details are taken after the age question, not at the end.
 *
 * The point is the people who never reach the end: before this, somebody who
 * stopped at question nine left nothing behind at all.
 */

use App\Models\Assessment;
use App\Models\Customer;
use App\Models\User;

beforeEach(function () {
    seedPlatform();
    seedQuestionnaire();
});

function answerQ(string $key, mixed $value)
{
    return test()->post('/assessment/answer', ['question_key' => $key, 'value' => $value]);
}

function startAssessment()
{
    return test()->get('/assessment');
}

it('asks for details as soon as the age question is answered', function () {
    startAssessment();
    answerQ('q1', 'new_will');

    // Not before.
    $this->get('/assessment')->assertInertia(fn ($p) => $p->component('Assessment/Question'));

    answerQ('q2', 'yes');

    $this->get('/assessment')->assertInertia(fn ($p) => $p->component('Assessment/Contact'));
});

it('does not ask an ineligible person for their details', function () {
    // Under 18 ends the journey at question two. Taking contact details from
    // somebody who has just been told they cannot use the service would be
    // collecting data with no purpose.
    startAssessment();
    answerQ('q1', 'new_will');
    answerQ('q2', 'no');

    $this->get('/assessment')->assertInertia(fn ($p) => $p->component('Assessment/Terminal'));
});

it('requires all three, and a real email', function () {
    startAssessment();
    answerQ('q1', 'new_will');
    answerQ('q2', 'yes');

    $this->post('/assessment/contact', [])
        ->assertSessionHasErrors(['contact_name', 'contact_email', 'contact_phone']);

    $this->post('/assessment/contact', [
        'contact_name' => 'Aisha Rahman',
        'contact_email' => 'not-an-email',
        'contact_phone' => '+971 50 123 4567',
    ])->assertSessionHasErrors('contact_email');
});

it('moves on once the details are given, and never asks twice', function () {
    startAssessment();
    answerQ('q1', 'new_will');
    answerQ('q2', 'yes');

    $this->post('/assessment/contact', [
        'contact_name' => 'Aisha Rahman',
        'contact_email' => 'aisha@example.com',
        'contact_phone' => '+971 50 123 4567',
    ])->assertSessionHasNoErrors();

    $assessment = Assessment::first();

    expect($assessment->hasContact())->toBeTrue()
        ->and($assessment->contact_name)->toBe('Aisha Rahman');

    // Back to questions, and the screen does not reappear.
    $this->get('/assessment')->assertInertia(fn ($p) => $p->component('Assessment/Question'));
});

it('keeps the lead when the person abandons half way', function () {
    // The entire reason this was moved earlier.
    startAssessment();
    answerQ('q1', 'new_will');
    answerQ('q2', 'yes');
    $this->post('/assessment/contact', [
        'contact_name' => 'Aisha Rahman',
        'contact_email' => 'aisha@example.com',
        'contact_phone' => '+971 50 123 4567',
    ]);
    answerQ('q3', 'GB');

    // They never come back.
    $followUp = Assessment::abandonedWithContact()->get();

    expect($followUp)->toHaveCount(1)
        ->and($followUp->first()->contact_email)->toBe('aisha@example.com')
        ->and($followUp->first()->completed_at)->toBeNull();
});

it('leaves a completed assessment off the follow-up list', function () {
    $assessment = Assessment::create([
        'questionnaire_version_id' => seedQuestionnaire()->id,
        'status' => 'completed',
        'contact_name' => 'Done Already',
        'contact_email' => 'done@example.com',
        'contact_captured_at' => now(),
        'completed_at' => now(),
    ]);

    expect(Assessment::abandonedWithContact()->count())->toBe(0)
        ->and($assessment->hasContact())->toBeTrue();
});

it('does not turn the assessment into an account', function () {
    // The promise on every screen is free, no account. Nothing here may create
    // a user, a password or a customer record.
    startAssessment();
    answerQ('q1', 'new_will');
    answerQ('q2', 'yes');
    $this->post('/assessment/contact', [
        'contact_name' => 'Aisha Rahman',
        'contact_email' => 'aisha@example.com',
        'contact_phone' => '+971 50 123 4567',
    ]);

    expect(User::where('email', 'aisha@example.com')->exists())->toBeFalse()
        ->and(Customer::where('email', 'aisha@example.com')->exists())->toBeFalse();
});
