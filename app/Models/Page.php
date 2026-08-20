<?php

namespace App\Models;

use App\Models\Concerns\RecordsActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Cache;

class Page extends Model
{
    use RecordsActivity, SoftDeletes;

    /** Site navigation is derived from this table, so it is cached and busted here. */
    public const NAV_CACHE = 'nav.pages';

    /** The legal column of the footer, in the order it is displayed. */
    private const LEGAL_KEYS = ['terms', 'privacy', 'refund', 'disclaimer', 'cookies'];

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

    /**
     * The footer's links, built from what is actually published.
     *
     * Hardcoding these in the Vue component meant unpublishing a page left a
     * link in the footer pointing at a 404. Reading them from the same column
     * the public route checks means the footer can never disagree with the
     * site, in either direction: unpublish the Cookie Policy and the link goes,
     * republish it after the cookie scan and the link returns with no deploy.
     *
     * @return array{pages: list<array{label: string, href: string}>, legal: list<array{label: string, href: string}>}
     */
    public static function navigation(): array
    {
        return Cache::remember(self::NAV_CACHE.'.'.app()->getLocale(), now()->addHour(), function () {
            $published = static::published()
                ->where('locale', app()->getLocale())
                ->orderBy('order')
                ->get(['key', 'slug', 'title']);

            $link = fn (Page $p) => ['label' => $p->title, 'href' => $p->slug];

            return [
                'pages' => $published
                    ->whereNotIn('key', [...self::LEGAL_KEYS, 'home'])
                    ->map($link)->values()->all(),
                'legal' => $published
                    ->whereIn('key', self::LEGAL_KEYS)
                    ->sortBy(fn (Page $p) => array_search($p->key, self::LEGAL_KEYS, true))
                    ->map($link)->values()->all(),
            ];
        });
    }

    protected static function booted(): void
    {
        // An unpublish has to take the footer link with it now, not in an hour.
        $forget = function () {
            foreach (config('app.supported_locales', ['en']) as $locale) {
                Cache::forget(self::NAV_CACHE.'.'.$locale);
            }
        };

        static::saved($forget);
        static::deleted($forget);
    }

    public function url(): string
    {
        return $this->slug === '/' ? url('/') : url($this->slug);
    }
}
