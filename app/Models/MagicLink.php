<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A single-use, time-limited, revocable link granting access to exactly ONE
 * case, with no session escalation.
 *
 * The raw token is returned once from issue() and then only ever exists in the
 * emailed URL. This table stores nothing but its SHA-256 hash, so a database
 * leak yields no usable links.
 */
class MagicLink extends Model
{
    protected $fillable = [
        'case_id', 'purpose', 'token_hash', 'expires_at',
        'used_at', 'revoked_at', 'revoked_by', 'ip_used', 'user_agent_used',
    ];

    protected $hidden = ['token_hash'];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'used_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function legalCase(): BelongsTo
    {
        return $this->belongsTo(LegalCase::class, 'case_id');
    }

    public function revokedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revoked_by');
    }

    public static function hash(string $rawToken): string
    {
        return hash('sha256', $rawToken);
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isUsed(): bool
    {
        return $this->used_at !== null;
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }

    /** Usable exactly once, before expiry, and only if never revoked. */
    public function isUsable(): bool
    {
        return ! $this->isUsed() && ! $this->isExpired() && ! $this->isRevoked();
    }

    /** Which of the four distinct failure screens to show. */
    public function failureReason(): ?string
    {
        return match (true) {
            $this->isRevoked() => 'revoked',
            $this->isUsed() => 'used',
            $this->isExpired() => 'expired',
            default => null,
        };
    }
}
