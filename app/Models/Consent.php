<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A consent record.
 *
 * `wording_hash` is what makes this evidential: it proves not merely that
 * something was agreed, but exactly WHAT wording was on screen at the time.
 */
class Consent extends Model
{
    protected $fillable = [
        'case_id', 'assessment_id', 'user_id', 'type', 'version', 'wording_hash',
        'accepted', 'ip_address', 'user_agent', 'language', 'related_reference', 'accepted_at',
    ];

    protected function casts(): array
    {
        return ['accepted' => 'boolean', 'accepted_at' => 'datetime'];
    }

    public function legalCase(): BelongsTo
    {
        return $this->belongsTo(LegalCase::class, 'case_id');
    }

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(Assessment::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function hashWording(string $wording): string
    {
        return hash('sha256', $wording);
    }
}
