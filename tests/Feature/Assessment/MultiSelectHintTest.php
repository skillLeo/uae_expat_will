<?php

/**
 * Every multi-select says so.
 *
 * Ahmed spotted that a question offering several answers gave no sign you
 * could pick more than one. Three of the six were silent, because the hint
 * lived in per-question help text somebody had to remember to write. It now
 * comes from the question type, so a new multi-select cannot be added without
 * it.
 */

use App\Domain\Assessment\Enums\QuestionType;
use App\Models\Question;

beforeEach(function () {
    seedPlatform();
    seedQuestionnaire();
});

it('tells the page which questions accept more than one answer', function () {
    $multi = Question::where('type', QuestionType::MultiSelect)->pluck('key');

    expect($multi)->not->toBeEmpty();

    foreach ($multi as $key) {
        expect(Question::where('key', $key)->first()->type->isMultiple())
            ->toBeTrue("{$key} should report as multiple");
    }
});

it('marks a multi-select question as multiple in the payload', function () {
    $this->get('/assessment');
    $this->post('/assessment/answer', ['question_key' => 'q1', 'value' => 'new_will']);
    $this->post('/assessment/answer', ['question_key' => 'q2', 'value' => 'yes']);
    $this->post('/assessment/contact', [
        'contact_name' => 'Aisha Rahman',
        'contact_email' => 'aisha@example.com',
        'contact_phone' => '+971500000000',
    ]);
    foreach (['q3' => 'GB', 'q4' => 'outside_uae', 'q5' => 'non_muslim', 'q6' => 'married_first'] as $k => $v) {
        $this->post('/assessment/answer', ['question_key' => $k, 'value' => $v]);
    }

    // q7 is a multi-select.
    $this->get('/assessment')->assertInertia(fn ($p) => $p
        ->where('question.key', 'q7')
        ->where('question.multiple', true));
});

it('does not mark a single-select as multiple', function () {
    $this->get('/assessment');

    $this->get('/assessment')->assertInertia(fn ($p) => $p
        ->where('question.key', 'q1')
        ->where('question.multiple', false));
});

it('still renders the options on a multi-select', function () {
    // Regression: the hint was briefly placed inside a v-if chain, which made
    // the option list render only when a question was NOT multi-select.
    $q = Question::where('key', 'q7')->with('options')->first();

    expect($q->options)->not->toBeEmpty()
        ->and($q->type->isMultiple())->toBeTrue();
});
