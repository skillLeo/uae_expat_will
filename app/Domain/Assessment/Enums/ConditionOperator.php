<?php

namespace App\Domain\Assessment\Enums;

enum ConditionOperator: string
{
    case Equals = 'equals';
    case NotEquals = 'not_equals';
    case In = 'in';
    case NotIn = 'not_in';
    case Answered = 'answered';
    case NotAnswered = 'not_answered';

    /**
     * Evaluate this operator against a stored answer.
     *
     * $answer may be a scalar (single select) or an array (multi select). For a
     * multi-select, `in` means "the selection INTERSECTS the expected set" — that
     * is what makes a rule like "Q10 includes a business" work.
     */
    public function matches(mixed $answer, mixed $expected): bool
    {
        $isAnswered = $answer !== null && $answer !== '' && $answer !== [];

        return match ($this) {
            self::Answered => $isAnswered,
            self::NotAnswered => ! $isAnswered,
            self::Equals => $this->normalise($answer) === $this->normalise($expected),
            self::NotEquals => $this->normalise($answer) !== $this->normalise($expected),
            self::In => $this->intersects($answer, $expected),
            self::NotIn => ! $this->intersects($answer, $expected),
        };
    }

    private function intersects(mixed $answer, mixed $expected): bool
    {
        $given = is_array($answer) ? $answer : [$answer];
        $want = is_array($expected) ? $expected : [$expected];

        return array_intersect(
            array_map(fn ($v) => (string) $v, $given),
            array_map(fn ($v) => (string) $v, $want),
        ) !== [];
    }

    private function normalise(mixed $value): mixed
    {
        if (is_array($value)) {
            $value = array_map(fn ($v) => (string) $v, $value);
            sort($value);

            return $value;
        }

        return is_scalar($value) ? (string) $value : $value;
    }

    public function label(): string
    {
        return match ($this) {
            self::Equals => 'is',
            self::NotEquals => 'is not',
            self::In => 'is any of',
            self::NotIn => 'is none of',
            self::Answered => 'has been answered',
            self::NotAnswered => 'has not been answered',
        };
    }
}
