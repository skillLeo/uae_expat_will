<?php

namespace App\Models;

use App\Models\Concerns\RecordsActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Faq extends Model
{
    use RecordsActivity, SoftDeletes;

    protected $fillable = ['category_key', 'order', 'question', 'answer', 'is_published', 'anchor', 'locale'];

    protected function casts(): array
    {
        return ['is_published' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::saving(function (self $faq) {
            $faq->anchor ??= Str::slug(Str::limit($faq->question, 60, ''));
        });
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(FaqCategory::class, 'category_key', 'key');
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }

    /**
     * Scope to a locale, defaulting to the active one.
     *
     * Without this a second language would render English answers under
     * translated category headings, which is worse than no translation at all.
     */
    public function scopeForLocale(Builder $query, ?string $locale = null): Builder
    {
        return $query->where('locale', $locale ?? app()->getLocale());
    }
}
