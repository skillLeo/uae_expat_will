<?php

namespace App\Domain\Assessment\DTOs;

/**
 * Progress through the assessment, expressed as NAMED STAGES.
 *
 * This deliberately carries no question count and no "3 of 16". The number of
 * questions depends on the answers, and the client has forbidden promising it.
 * `percent` is a growing estimate derived from the stage, never from
 * answered/total — a fraction with a denominator IS a promised count.
 */
final class Progress
{
    /** @param array<int, array{key: string, label: string, state: string}> $stages */
    public function __construct(
        public readonly string $currentStageKey,
        public readonly string $currentStageLabel,
        public readonly array $stages,
        public readonly int $percent,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'current_stage_key' => $this->currentStageKey,
            'current_stage_label' => $this->currentStageLabel,
            'stages' => $this->stages,
            'percent' => $this->percent,
        ];
    }
}
