<?php

use App\Domain\Assessment\Services\AnswerValidator;
use App\Models\Question;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    seedPlatform();
    seedQuestionnaire();
    $this->validator = app(AnswerValidator::class);
});

function question(string $key): Question
{
    return Question::where('key', $key)->with('options')->firstOrFail();
}

it('drops every other option when the exclusive option is selected', function () {
    // The client component does this too, but the client is not a line of
    // defence: a tampered payload must produce the same stored answer.
    $result = $this->validator->validate(
        question('q15a'),
        ['none', 'family_dispute', 'insolvency'],
    );

    expect($result)->toBe(['none']);
});

it('keeps a normal multi-select intact', function () {
    $result = $this->validator->validate(question('q15a'), ['family_dispute', 'insolvency']);

    expect($result)->toEqualCanonicalizing(['family_dispute', 'insolvency']);
});

it('enforces exclusivity on every question that has an exclusive option', function (string $key, string $exclusive, string $other) {
    expect($this->validator->validate(question($key), [$exclusive, $other]))->toBe([$exclusive]);
})->with([
    ['q7', 'none', 'adult_only'],
    ['q12', 'none', 'foreign_will'],
    ['q15a', 'none', 'insolvency'],
]);

it('rejects an option that does not belong to the question', function () {
    $this->validator->validate(question('q1'), 'not_a_real_option');
})->throws(ValidationException::class);

it('rejects an unknown country', function () {
    $this->validator->validate(question('q3'), 'ZZ');
})->throws(ValidationException::class);

it('accepts a valid country', function () {
    expect($this->validator->validate(question('q3'), 'GB'))->toBe('GB');
});

it('rejects a blank answer to a required question', function () {
    $this->validator->validate(question('q1'), null);
})->throws(ValidationException::class);

it('deduplicates repeated selections', function () {
    $result = $this->validator->validate(question('q10'), ['bank', 'bank', 'real_estate']);

    expect($result)->toHaveCount(2);
});
