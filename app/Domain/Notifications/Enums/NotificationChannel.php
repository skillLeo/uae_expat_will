<?php

namespace App\Domain\Notifications\Enums;

enum NotificationChannel: string
{
    case Email = 'email';
    case Whatsapp = 'whatsapp';

    public function label(): string
    {
        return match ($this) {
            self::Email => 'Email',
            self::Whatsapp => 'WhatsApp',
        };
    }

    /**
     * The channel to fall back to when this one fails.
     * Email is the backstop and falls back to nothing.
     */
    public function fallback(): ?self
    {
        return $this === self::Whatsapp ? self::Email : null;
    }
}
