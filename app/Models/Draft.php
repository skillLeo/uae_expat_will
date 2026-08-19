<?php

namespace App\Models;

use App\Models\Concerns\RecordsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Draft extends Model implements HasMedia
{
    use InteractsWithMedia, RecordsActivity, SoftDeletes;

    protected $fillable = [
        'case_id', 'version_number', 'media_id', 'status',
        'sent_at', 'approved_at', 'approved_by_customer',
    ];

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
            'approved_at' => 'datetime',
            'approved_by_customer' => 'boolean',
        ];
    }

    public function legalCase(): BelongsTo
    {
        return $this->belongsTo(LegalCase::class, 'case_id');
    }

    public function amendments(): HasMany
    {
        return $this->hasMany(DraftAmendment::class);
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('draft')->useDisk('private')->singleFile();
    }

    /** How many amendment rounds this draft has consumed from the allowance. */
    public function amendmentsUsed(): int
    {
        return $this->amendments()->where('is_within_allowance', true)->count();
    }
}
