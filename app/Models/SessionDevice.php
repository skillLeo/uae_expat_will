<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SessionDevice extends Model
{
    protected $table = 'sessions_devices';

    protected $fillable = [
        'user_id', 'session_id', 'ip', 'user_agent', 'device_label', 'last_active_at', 'revoked_at',
    ];

    protected function casts(): array
    {
        return ['last_active_at' => 'datetime', 'revoked_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isActive(): bool
    {
        return $this->revoked_at === null;
    }
}
