<?php

namespace App\Models;

use App\Domain\Notifications\Enums\NotificationChannel;
use App\Domain\Notifications\Enums\NotificationStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationLog extends Model
{
    protected $fillable = [
        'case_id', 'template_key', 'channel', 'recipient', 'status',
        'provider_reference', 'error', 'payload', 'sent_at', 'delivered_at',
        'failed_at', 'fallback_of_id',
    ];

    protected function casts(): array
    {
        return [
            'channel' => NotificationChannel::class,
            'status' => NotificationStatus::class,
            'payload' => 'array',
            'sent_at' => 'datetime',
            'delivered_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }

    public function legalCase(): BelongsTo
    {
        return $this->belongsTo(LegalCase::class, 'case_id');
    }

    /** The WhatsApp message this email was sent to replace, if any. */
    public function fallbackOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'fallback_of_id');
    }
}
