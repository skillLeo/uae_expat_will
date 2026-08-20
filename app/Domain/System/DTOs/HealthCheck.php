<?php

namespace App\Domain\System\DTOs;

use App\Domain\System\Enums\HealthState;

final class HealthCheck
{
    /**
     * @param  array<string, string|int|null>  $detail  short key/value facts
     * @param  string|null  $consequence  what this failing actually means, in plain English
     * @param  string|null  $fix  what to do about it
     * @param  bool  $fixIsHostPanel  true when the fix is an hPanel action, not a click here
     */
    public function __construct(
        public readonly string $key,
        public readonly string $label,
        public readonly HealthState $state,
        public readonly string $summary,
        public readonly array $detail = [],
        public readonly ?string $consequence = null,
        public readonly ?string $fix = null,
        public readonly bool $fixIsHostPanel = false,
    ) {}

    /** A check that could not run. Never reported as healthy. */
    public static function unknown(string $key, string $label, string $why): self
    {
        return new self(
            key: $key,
            label: $label,
            state: HealthState::Unknown,
            summary: 'This check could not run.',
            detail: ['reason' => $why],
            consequence: 'We cannot tell whether this part of the platform is working.',
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'label' => $this->label,
            'state' => $this->state->value,
            'state_label' => $this->state->label(),
            'tone' => $this->state->tone(),
            'summary' => $this->summary,
            'detail' => $this->detail,
            'consequence' => $this->consequence,
            'fix' => $this->fix,
            'fix_is_host_panel' => $this->fixIsHostPanel,
        ];
    }
}
