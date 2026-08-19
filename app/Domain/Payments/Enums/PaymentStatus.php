<?php

namespace App\Domain\Payments\Enums;

enum PaymentStatus: string
{
    case Pending = 'pending';
    case Paid = 'paid';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
    case PartiallyPaid = 'partially_paid';
    case Refunded = 'refunded';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Paid => 'Paid',
            self::Failed => 'Failed',
            self::Cancelled => 'Cancelled',
            self::PartiallyPaid => 'Partially Paid',
            self::Refunded => 'Refunded',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Paid => 'positive',
            self::Pending, self::PartiallyPaid => 'attention',
            self::Failed => 'critical',
            self::Cancelled, self::Refunded => 'neutral',
        };
    }

    public function isSettled(): bool
    {
        return in_array($this, [self::Paid, self::Refunded], true);
    }
}
