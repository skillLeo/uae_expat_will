<?php

namespace App\Domain\Assessment\Services;

use App\Domain\Assessment\Enums\QuestionType;
use App\Models\Question;
use Illuminate\Validation\ValidationException;

/**
 * Server-side revalidation of a single answer.
 *
 * The Vue components enforce the same rules for the sake of the interaction, but
 * the client is not a line of defence. Everything below is re-checked here on
 * every submitted step, because the browser is under the user's control and the
 * exclusive-option rule in particular has a real legal consequence: "None of
 * these" combined with "there is an active dispute" would silently route a case
 * that should be held.
 */
class AnswerValidator
{
    /**
     * @return mixed the normalised value to store
     *
     * @throws ValidationException
     */
    public function validate(Question $question, mixed $value): mixed
    {
        $value = $this->normalise($question, $value);

        if ($question->is_required && $this->isBlank($value)) {
            $this->fail($question, 'This question must be answered before you can continue.');
        }

        if ($this->isBlank($value)) {
            return $value;
        }

        return match ($question->type) {
            QuestionType::SingleSelect => $this->validateSingleSelect($question, $value),
            QuestionType::MultiSelect => $this->validateMultiSelect($question, $value),
            QuestionType::CountrySelect => $this->validateCountry($question, $value),
            QuestionType::Boolean => (bool) $value,
            QuestionType::Number => $this->validateNumber($question, $value),
            QuestionType::Date => $this->validateDate($question, $value),
            QuestionType::Text, QuestionType::Textarea => $this->validateText($question, $value),
        };
    }

    private function validateSingleSelect(Question $question, mixed $value): string
    {
        $value = is_array($value) ? reset($value) : $value;
        $valid = $question->options->pluck('key')->all();

        if (! in_array((string) $value, $valid, true)) {
            $this->fail($question, 'That is not one of the available answers.');
        }

        return (string) $value;
    }

    /**
     * @return array<int, string>
     */
    private function validateMultiSelect(Question $question, mixed $value): array
    {
        $selected = array_values(array_unique(array_map(
            'strval',
            is_array($value) ? $value : [$value],
        )));

        $valid = $question->options->pluck('key')->all();

        foreach ($selected as $key) {
            if (! in_array($key, $valid, true)) {
                $this->fail($question, 'That is not one of the available answers.');
            }
        }

        // The exclusive option ("None of these") cannot be combined with anything.
        // If it is present alongside others, the exclusive one wins and the rest
        // are dropped — mirroring what the component does, so a tampered payload
        // produces the same stored answer as an honest one.
        $exclusive = $question->exclusiveOption();

        if ($exclusive && in_array($exclusive->key, $selected, true) && count($selected) > 1) {
            return [$exclusive->key];
        }

        return $selected;
    }

    private function validateCountry(Question $question, mixed $value): string
    {
        $value = (string) (is_array($value) ? reset($value) : $value);
        $countries = array_keys(config('countries.list', []));

        if ($countries !== [] && ! in_array($value, $countries, true)) {
            $this->fail($question, 'Please choose a country from the list.');
        }

        return $value;
    }

    private function validateNumber(Question $question, mixed $value): int|float
    {
        if (! is_numeric($value)) {
            $this->fail($question, 'Please enter a number.');
        }

        $number = $value + 0;

        if ($question->min !== null && $number < $question->min) {
            $this->fail($question, "Please enter a number of at least {$question->min}.");
        }

        if ($question->max !== null && $number > $question->max) {
            $this->fail($question, "Please enter a number no greater than {$question->max}.");
        }

        return $number;
    }

    private function validateDate(Question $question, mixed $value): string
    {
        if (strtotime((string) $value) === false) {
            $this->fail($question, 'Please enter a valid date.');
        }

        return (string) $value;
    }

    private function validateText(Question $question, mixed $value): string
    {
        $text = trim((string) $value);
        $max = $question->max ?? 2000;

        if (mb_strlen($text) > $max) {
            $this->fail($question, "Please keep this under {$max} characters.");
        }

        return $text;
    }

    private function normalise(Question $question, mixed $value): mixed
    {
        if ($question->type === QuestionType::MultiSelect) {
            return is_array($value) ? $value : ($this->isBlank($value) ? [] : [$value]);
        }

        return is_array($value) && count($value) === 1 ? reset($value) : $value;
    }

    private function isBlank(mixed $value): bool
    {
        return $value === null || $value === '' || $value === [];
    }

    /**
     * @throws ValidationException
     */
    private function fail(Question $question, string $message): never
    {
        throw ValidationException::withMessages([
            "answers.{$question->key}" => $message,
        ]);
    }
}
