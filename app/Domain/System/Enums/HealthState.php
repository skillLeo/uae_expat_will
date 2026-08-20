<?php

namespace App\Domain\System\Enums;

enum HealthState: string
{
    case Healthy = 'healthy';
    case Warning = 'warning';
    case Critical = 'critical';

    /**
     * A check that could not run.
     *
     * Distinct from healthy on purpose: "we could not tell" must never be shown
     * as "fine". It is also distinct from critical, so a broken check does not
     * cry wolf and does not fire an alert.
     */
    case Unknown = 'unknown';

    public function label(): string
    {
        return match ($this) {
            self::Healthy => 'Healthy',
            self::Warning => 'Needs attention',
            self::Critical => 'Not working',
            self::Unknown => 'Cannot tell',
        };
    }

    /** Maps onto the product's existing semantic pills. */
    public function tone(): string
    {
        return match ($this) {
            self::Healthy => 'positive',
            self::Warning => 'attention',
            self::Critical => 'critical',
            self::Unknown => 'neutral',
        };
    }

    public function severity(): int
    {
        return match ($this) {
            self::Critical => 3,
            self::Warning => 2,
            self::Unknown => 1,
            self::Healthy => 0,
        };
    }

    public function isAlertable(): bool
    {
        return $this === self::Critical;
    }
}
