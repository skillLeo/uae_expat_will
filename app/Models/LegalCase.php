<?php

namespace App\Models;

use App\Domain\Cases\Enums\CaseStage;
use App\Domain\Cases\Enums\CaseStatus;
use App\Domain\Cases\Enums\InternalStatus;
use App\Models\Concerns\RecordsActivity;
use App\Models\Scopes\RestrictedCaseScope;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Crypt;

#[ScopedBy([RestrictedCaseScope::class])]
class LegalCase extends Model
{
    use RecordsActivity, SoftDeletes;

    protected $table = 'cases';

    /** The restricted reason must never reach the audit log either. */
    protected array $logAttributes = [
        'status', 'internal_status', 'assigned_to', 'is_restricted',
        'paid_amount', 'closed_at', 'closed_reason',
    ];

    protected string $logName = 'case';

    protected $fillable = [
        'reference', 'customer_id', 'assessment_id', 'pathway', 'status',
        'internal_status', 'assigned_to', 'countdown_due_at', 'is_restricted',
        'restricted_visible_to', 'service_type', 'quoted_amount', 'currency',
        'paid_amount', 'notes_count', 'last_contact_at', 'closed_at', 'closed_reason',
    ];

    /** Never serialise the ciphertext, under any circumstances. */
    protected $hidden = ['restricted_reason_encrypted'];

    protected function casts(): array
    {
        return [
            'status' => CaseStatus::class,
            'internal_status' => InternalStatus::class,
            'is_restricted' => 'boolean',
            'restricted_visible_to' => 'array',
            'quoted_amount' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'countdown_due_at' => 'datetime',
            'last_contact_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    // ------------------------------------------------------------- relationships

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(Assessment::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(CaseStatusHistory::class, 'case_id')->latest('changed_at');
    }

    public function notes(): HasMany
    {
        return $this->hasMany(CaseNote::class, 'case_id')->latest();
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(CaseContact::class, 'case_id')->latest('occurred_at');
    }

    public function stageTimestamps(): HasMany
    {
        return $this->hasMany(CaseStageTimestamp::class, 'case_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'case_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class, 'case_id');
    }

    public function drafts(): HasMany
    {
        return $this->hasMany(Draft::class, 'case_id')->orderByDesc('version_number');
    }

    public function magicLinks(): HasMany
    {
        return $this->hasMany(MagicLink::class, 'case_id');
    }

    public function notificationLogs(): HasMany
    {
        return $this->hasMany(NotificationLog::class, 'case_id');
    }

    // ------------------------------------------------------------------ restricted

    /**
     * The restricted reason, in clear.
     *
     * Returns null for anyone without the permission. In practice the column is
     * not even selected for them (see RestrictedCaseScope), so this is the second
     * of the two locks rather than the first.
     */
    public function restrictedReason(): ?string
    {
        if (! $this->is_restricted || ! RestrictedCaseScope::viewerMaySeeRestricted()) {
            return null;
        }

        $cipher = $this->attributes['restricted_reason_encrypted'] ?? null;

        if ($cipher === null) {
            return null;
        }

        try {
            return Crypt::decryptString($cipher);
        } catch (\Throwable) {
            return null;
        }
    }

    public function setRestrictedReason(?string $reason): void
    {
        $this->attributes['restricted_reason_encrypted'] = $reason === null
            ? null
            : Crypt::encryptString($reason);
    }

    /** Whether the current viewer may read this case's body at all. */
    public function isReadableByViewer(): bool
    {
        return ! $this->is_restricted || RestrictedCaseScope::viewerMaySeeRestricted();
    }

    public const REDACTION = 'Restricted — authorised legal staff only';

    // ---------------------------------------------------------------------- scopes

    /**
     * Search.
     *
     * Deliberately limited to the reference and the customer's name and email.
     * It never touches notes, trigger reasons or the restricted reason — a search
     * that matches on hidden content leaks that content by revealing which query
     * found it.
     */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (blank($term)) {
            return $query;
        }

        $term = '%'.trim($term).'%';

        return $query->where(function (Builder $q) use ($term) {
            $q->where('cases.reference', 'like', $term)
                ->orWhereHas('customer', fn (Builder $c) => $c
                    ->where('full_name', 'like', $term)
                    ->orWhere('email', 'like', $term));
        });
    }

    /** Cases a given user is allowed to list, honouring cases.view.assigned. */
    public function scopeVisibleTo(Builder $query, ?User $user): Builder
    {
        if ($user === null) {
            return $query->whereRaw('1 = 0');
        }

        if ($user->can('cases.view.all')) {
            return $query;
        }

        if ($user->can('cases.view.assigned')) {
            return $query->where('cases.assigned_to', $user->id);
        }

        return $query->whereRaw('1 = 0');
    }

    public function scopeOverdue(Builder $query): Builder
    {
        return $query->whereNotNull('countdown_due_at')
            ->where('countdown_due_at', '<', now())
            ->whereNull('closed_at');
    }

    // ----------------------------------------------------------------- behaviour

    public function stageAt(CaseStage $stage): ?CaseStageTimestamp
    {
        return $this->stageTimestamps->firstWhere('stage', $stage->value);
    }

    public function hasReachedStage(CaseStage $stage): bool
    {
        return $this->stageAt($stage) !== null;
    }

    public function isOverdue(): bool
    {
        return $this->countdown_due_at !== null
            && $this->countdown_due_at->isPast()
            && $this->closed_at === null;
    }

    /** Payment is never offered on a held matter. The control must not exist. */
    public function allowsPayment(): bool
    {
        return ! $this->is_restricted && $this->internal_status->allowsPayment();
    }
}
