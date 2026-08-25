<?php

/**
 * A stop must block progress, not trap the person.
 *
 * Ahmed hit this on the live site: one mis-click on the age question and the
 * session was dead. Back did nothing, every other answer returned the same
 * screen, and the only way out was clearing cookies. Somebody who meant to
 * click "yes" is not a person to punish.
 */

use App\Models\Assessment;

beforeEach(function () {
    seedPlatform();
    seedQuestionnaire();
    $this->get('/assessment');
    $this->post('/assessment/answer', ['question_key' => 'q1', 'value' => 'new_will']);
});

it('lets someone correct the answer that stopped them', function () {
    $this->post('/assessment/answer', ['question_key' => 'q2', 'value' => 'no']);
    $this->get('/assessment')->assertInertia(fn ($p) => $p->component('Assessment/Terminal'));

    // The mis-click, corrected.
    $this->post('/assessment/answer', ['question_key' => 'q2', 'value' => 'yes']);

    $this->get('/assessment')->assertInertia(fn ($p) => $p->component('Assessment/Contact'));
});

it('still refuses to let them answer past the stop', function () {
    $this->post('/assessment/answer', ['question_key' => 'q2', 'value' => 'no']);

    // q3 has never been answered, so this is progress, not correction.
    $this->post('/assessment/answer', ['question_key' => 'q3', 'value' => 'GB']);

    expect(Assessment::first()->load('answers')->answerSet()->get('q3'))->toBeNull();
    $this->get('/assessment')->assertInertia(fn ($p) => $p->component('Assessment/Terminal'));
});

it('offers a clean start when someone would rather begin again', function () {
    $this->post('/assessment/answer', ['question_key' => 'q2', 'value' => 'no']);
    $first = Assessment::first();

    $this->post('/assessment/restart')->assertRedirect('/assessment');

    $this->get('/assessment')->assertInertia(fn ($p) => $p
        ->component('Assessment/Question')
        ->where('question.key', 'q1'));

    // The abandoned one is kept, marked where they left it — it is still a lead.
    expect(Assessment::count())->toBe(2)
        ->and($first->fresh()->abandoned_at_question_key)->not->toBeNull();
});

it('does not lose a corrected answer to a stale cursor', function () {
    $this->post('/assessment/answer', ['question_key' => 'q2', 'value' => 'no']);
    $this->post('/assessment/answer', ['question_key' => 'q2', 'value' => 'yes']);

    expect(Assessment::first()->load('answers')->answerSet()->get('q2'))->toBe('yes');
});

it('lets a UAE citizen correct their nationality too', function () {
    // Not just the age question — every stop has to be recoverable.
    $this->post('/assessment/answer', ['question_key' => 'q2', 'value' => 'yes']);
    $this->post('/assessment/contact', [
        'contact_name' => 'Aisha Rahman',
        'contact_email' => 'aisha@example.com',
        'contact_phone' => '+971500000000',
    ]);
    $this->post('/assessment/answer', ['question_key' => 'q3', 'value' => 'AE']);
    $this->get('/assessment')->assertInertia(fn ($p) => $p->component('Assessment/Terminal'));

    $this->post('/assessment/answer', ['question_key' => 'q3', 'value' => 'GB']);

    $this->get('/assessment')->assertInertia(fn ($p) => $p->component('Assessment/Question'));
});
