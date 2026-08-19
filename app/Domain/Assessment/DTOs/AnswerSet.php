<?php

namespace App\Domain\Assessment\DTOs;

/**
 * The complete set of answers given so far, keyed by question key.
 *
 * The engine needs the WHOLE answer set in scope at once rather than the current
 * question alone, because rules like R-11 read an earlier stored answer:
 * "Q13A wish outside immediate family AND Q5 = Muslim". A question-by-question
 * evaluator cannot express that.
 */
final class AnswerSet
{
    /** @param array<string, mixed> $answers question_key => value */
    public function __construct(private array $answers = []) {}

    /** @param array<string, mixed> $answers */
    public static function make(array $answers = []): self
    {
        return new self($answers);
    }

    public function get(string $questionKey): mixed
    {
        return $this->answers[$questionKey] ?? null;
    }

    public function has(string $questionKey): bool
    {
        $value = $this->answers[$questionKey] ?? null;

        return $value !== null && $value !== '' && $value !== [];
    }

    public function with(string $questionKey, mixed $value): self
    {
        $clone = $this->answers;
        $clone[$questionKey] = $value;

        return new self($clone);
    }

    public function without(string $questionKey): self
    {
        $clone = $this->answers;
        unset($clone[$questionKey]);

        return new self($clone);
    }

    /** @return array<string, mixed> */
    public function all(): array
    {
        return $this->answers;
    }

    /** @return array<int, string> */
    public function keys(): array
    {
        return array_keys($this->answers);
    }

    public function count(): int
    {
        return count($this->answers);
    }

    public function isEmpty(): bool
    {
        return $this->answers === [];
    }
}
