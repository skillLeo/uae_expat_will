<?php

namespace App\Models;

use App\Domain\Assessment\DTOs\AnswerSet;
use App\Domain\Assessment\Enums\Outcome;
use App\Domain\Assessment\RoutingEngine;
use App\Models\Concerns\RecordsActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Assessment extends Model
{
    use RecordsActivity, SoftDeletes;

    /** Answers are never logged — they are the sensitive part. */
    protected array $logAttributes = ['status', 'outcome', 'completed_at'];

    protected $fillable = [
        'uuid', 'session_token', 'questionnaire_version_id', 'status', 'outcome',
        'outcome_detail', 'trigger_reasons', 'flags', 'reminders', 'route_marks',
        'current_question_key', 'abandoned_at_question_key', 'source', 'campaign',
        'utm', 'referrer', 'ip_address', 'user_agent', 'started_at', 'completed_at',
        'expires_at', 'contact_name', 'contact_email', 'contact_phone',
        'contact_captured_at',
        'partner_name', 'partner_nationality', 'partner_phone', 'partner_email',
    ];

    protected $hidden = ['session_token'];

    protected function casts(): array
    {
        return [
            'outcome' => Outcome::class,
            'trigger_reasons' => 'array',
            'flags' => 'array',
            'reminders' => 'array',
            'route_marks' => 'array',
            'utm' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'expires_at' => 'datetime',
            'contact_captured_at' => 'datetime',
        ];
    }

    public function hasContact(): bool
    {
        return $this->contact_captured_at !== null && $this->contact_email !== null;
    }

    /** @return array{full_name: string|null, email: string|null, phone: string|null} */
    public function contact(): array
    {
        return [
            'full_name' => $this->contact_name,
            'email' => $this->contact_email,
            'phone' => $this->contact_phone,
        ];
    }

    /**
     * The partner on a mirror-Wills assessment.
     *
     * @return array{name: string|null, nationality: string|null, phone: string|null, email: string|null}
     */
    public function partner(): array
    {
        return [
            'name' => $this->partner_name,
            'nationality' => $this->partner_nationality,
            'phone' => $this->partner_phone,
            'email' => $this->partner_email,
        ];
    }

    /** Two Wills were asked for at question one. */
    public function isMirror(): bool
    {
        return $this->answerSet()->get('q1') === 'two_wills';
    }

    /**
     * Started, gave us their details, and never finished. This is the follow-up
     * list — the entire reason the details are collected early.
     *
     * @param  Builder<Assessment>  $query
     */
    public function scopeAbandonedWithContact($query)
    {
        return $query->whereNotNull('contact_captured_at')->whereNull('completed_at');
    }

    protected static function booted(): void
    {
        static::creating(function (self $assessment) {
            $assessment->uuid ??= (string) Str::uuid();
            // 64 hex chars from 32 random bytes. This token is the only thing
            // standing between a stranger and a resumed assessment, so it is
            // generated the same way a session id is.
            $assessment->session_token ??= bin2hex(random_bytes(32));
            $assessment->started_at ??= now();
            $assessment->expires_at ??= now()->addDays(
                (int) setting('retention.incomplete_assessment_days', 30)
            );
        });
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(QuestionnaireVersion::class, 'questionnaire_version_id');
    }

    public function answers(): HasMany
    {
        return $this->hasMany(AssessmentAnswer::class);
    }

    public function legalCase(): HasOne
    {
        return $this->hasOne(LegalCase::class, 'assessment_id');
    }

    public function consents(): HasMany
    {
        return $this->hasMany(Consent::class);
    }

    /** The answer set, ready for the engine. */
    public function answerSet(): AnswerSet
    {
        return AnswerSet::make(
            $this->answers->pluck('value', 'question_key')->all()
        );
    }

    public function engine(): RoutingEngine
    {
        return new RoutingEngine($this->version);
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }
}
