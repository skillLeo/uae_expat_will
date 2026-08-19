<?php

use App\Domain\Settings\Services\SettingsRepository;

if (! function_exists('setting')) {
    /**
     * Read a runtime setting.
     *
     * Falls back to the supplied default when the settings table does not exist
     * yet, so migrations and the very first seed run do not explode.
     */
    function setting(string $key, mixed $default = null): mixed
    {
        try {
            return app(SettingsRepository::class)->get($key, $default);
        } catch (Throwable) {
            return $default;
        }
    }
}

if (! function_exists('feature')) {
    /** Feature flags are false unless explicitly enabled. */
    function feature(string $flag): bool
    {
        try {
            return app(SettingsRepository::class)->feature($flag);
        } catch (Throwable) {
            return false;
        }
    }
}

if (! function_exists('money')) {
    /** Format money the way the design does: tabular, two decimals, AED prefix. */
    function money(int|float|string|null $amount, string $currency = 'AED'): string
    {
        return $currency.' '.number_format((float) $amount, 2);
    }
}
