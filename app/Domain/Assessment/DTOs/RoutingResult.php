<?php

namespace App\Domain\Assessment\DTOs;

use App\Domain\Assessment\Enums\Outcome;

/**
 * What the engine decided, and everything the case record needs to carry out of
 * the assessment (Part 6.3 of the specification).
 */
final class RoutingResult
{
    /**
     * @param  array<int, TriggerReason>  $triggerReasons
     * @param  array<int, string>  $flags  seen by the legal reviewer after payment
     * @param  array<int, string>  $reminders  owed in the detailed questionnaire
     * @param  array<int, string>  $routeMarks  e.g. the wider Dubai drafting route
     * @param  array<int, string>  $matchedRules  names of every rule that fired
     */
    public function __construct(
        public readonly Outcome $outcome,
        public readonly ?string $outcomeDetail = null,
        public readonly array $triggerReasons = [],
        public readonly array $flags = [],
        public readonly array $reminders = [],
        public readonly array $routeMarks = [],
        public readonly array $matchedRules = [],
        public readonly bool $isComplete = true,
    ) {}

    public function isTerminal(): bool
    {
        return $this->outcome->isTerminal();
    }

    /**
     * Whether this case must be restricted.
     *
     * True when the outcome itself is restricted, or when ANY trigger reason is.
     * A case can be held for an ordinary reason AND carry a restricted one — the
     * whole case is then restricted, because otherwise the ordinary reasons would
     * narrow down what the restricted one must be.
     */
    public function isRestricted(): bool
    {
        if ($this->outcome->isRestricted()) {
            return true;
        }

        foreach ($this->triggerReasons as $reason) {
            if ($reason->isRestricted) {
                return true;
            }
        }

        return false;
    }

    public function allowsPayment(): bool
    {
        return $this->outcome->allowsPayment();
    }

    /** @return array<int, array<string, mixed>> */
    public function triggerReasonsArray(bool $includeRestricted = true): array
    {
        return array_map(
            fn (TriggerReason $r) => $r->isRestricted && ! $includeRestricted
                ? $r->toRedactedArray()
                : $r->toArray(),
            $this->triggerReasons,
        );
    }

    /** How many triggers fired, including restricted ones. Always safe to show. */
    public function triggerCount(): int
    {
        return count($this->triggerReasons);
    }
}
