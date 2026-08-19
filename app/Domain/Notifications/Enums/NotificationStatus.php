<?php

namespace App\Domain\Notifications\Enums;

enum NotificationStatus: string
{
    case Queued = 'queued';
    case Sent = 'sent';
    case Delivered = 'delivered';
    case Failed = 'failed';
    case Read = 'read';

    public function tone(): string
    {
        return match ($this) {
            self::Delivered, self::Read => 'positive',
            self::Sent => 'progress',
            self::Queued => 'neutral',
            self::Failed => 'critical',
        };
    }

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
