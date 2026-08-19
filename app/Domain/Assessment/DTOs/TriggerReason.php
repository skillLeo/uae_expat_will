<?php

namespace App\Domain\Assessment\DTOs;

use App\Domain\Assessment\Enums\Outcome;

/**
 * One itemised reason a case landed where it did.
 *
 * The signed contract requires trigger reasons in the case detail, itemised by
 * question — not a single opaque "held for review". A reviewer opening a case
 * must be able to see which answer caused which outcome, and under which rule.
 */
final class TriggerReason
{
    public function __construct(
        public readonly string $ruleName,
        public readonly Outcome $outcome,
        public readonly string $questionKey,
        public readonly string $questionPrompt,
        public readonly string $answerLabel,
        public readonly ?string $detail = null,
        /** Restricted reasons are readable only with cases.view_restricted. */
        public readonly bool $isRestricted = false,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'rule_name' => $this->ruleName,
            'outcome' => $this->outcome->value,
            'question_key' => $this->questionKey,
            'question_prompt' => $this->questionPrompt,
            'answer_label' => $this->answerLabel,
            'detail' => $this->detail,
            'is_restricted' => $this->isRestricted,
        ];
    }

    /**
     * The safe form: enough to know a trigger EXISTS, with nothing about what it
     * was. This is what an unauthorised viewer, an export or a notification gets.
     */
    public function toRedactedArray(): array
    {
        return [
            'rule_name' => null,
            'outcome' => $this->outcome->value,
            'question_key' => null,
            'question_prompt' => 'Restricted — authorised legal staff only',
            'answer_label' => 'Restricted — authorised legal staff only',
            'detail' => null,
            'is_restricted' => true,
        ];
    }
}
