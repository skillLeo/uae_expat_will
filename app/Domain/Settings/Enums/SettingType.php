<?php

namespace App\Domain\Settings\Enums;

enum SettingType: string
{
    case String = 'string';
    case Text = 'text';
    case Boolean = 'boolean';
    case Integer = 'integer';
    case Json = 'json';
    case Encrypted = 'encrypted';
    case File = 'file';

    public function cast(?string $raw): mixed
    {
        if ($raw === null) {
            return null;
        }

        return match ($this) {
            self::Boolean => filter_var($raw, FILTER_VALIDATE_BOOLEAN),
            self::Integer => (int) $raw,
            self::Json => json_decode($raw, true),
            default => $raw,
        };
    }

    public function serialise(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return match ($this) {
            self::Boolean => $value ? '1' : '0',
            self::Json => json_encode($value),
            default => (string) $value,
        };
    }

    /** An encrypted value is never shown in full, in the UI or in history. */
    public function isSecret(): bool
    {
        return $this === self::Encrypted;
    }
}
