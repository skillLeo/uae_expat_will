<?php

namespace App\Models;

use App\Models\Concerns\RecordsActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Page extends Model
{
    use RecordsActivity, SoftDeletes;

    protected $fillable = [
        'key', 'slug', 'title', 'seo_title', 'meta_description', 'breadcrumb',
        'structured_data', 'is_published', 'published_at', 'order', 'locale',
    ];

    protected function casts(): array
    {
        return [
            'structured_data' => 'array',
            'is_published' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    public function sections(): HasMany
    {
        return $this->hasMany(PageSection::class)->orderBy('order');
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }

    public function url(): string
    {
        return $this->slug === '/' ? url('/') : url($this->slug);
    }
}
