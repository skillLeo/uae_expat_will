<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;

/**
 * One stored answer.
 *
 * A sensitive answer (religion, capacity, family detail) is encrypted at rest.
 * The encryption is conditional per question rather than blanket, so ordinary
 * answers stay queryable for analytics while the sensitive ones are unreadable
 * in a database dump.
 */
class AssessmentAnswer extends Model
{
    protected $fillable = [
        'assessment_id', 'question_id', 'question_key', 'value', 'is_encrypted', 'answered_at',
    ];

    protected function casts(): array
    {
        return ['is_encrypted' => 'boolean', 'answered_at' => 'datetime'];
    }

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(Assessment::class);
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }

    /**
     * Value is stored as JSON, and encrypted on top of that when sensitive.
     * Decryption failures return null rather than throwing, so one unreadable
     * row cannot take down a whole case detail screen.
     */
    public function getValueAttribute(?string $raw): mixed
    {
        if ($raw === null) {
            return null;
        }

        if ($this->is_encrypted) {
            try {
                $raw = Crypt::decryptString($raw);
            } catch (\Throwable) {
                return null;
            }
        }

        return json_decode($raw, true);
    }

    public function setValueAttribute(mixed $value): void
    {
        $json = json_encode($value);

        $this->attributes['value'] = $this->is_encrypted
            ? Crypt::encryptString($json)
            : $json;
    }
}
