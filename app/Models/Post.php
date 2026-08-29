<?php

namespace App\Models;

use App\Domain\Content\Services\HtmlSanitiser;
use App\Models\Concerns\RecordsActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class Post extends Model
{
    use RecordsActivity, SoftDeletes;

    /** Average adult reading speed. Used only to set an expectation. */
    private const WORDS_PER_MINUTE = 220;

    protected $fillable = [
        'slug', 'title', 'category', 'excerpt', 'body', 'author_name',
        'author_title', 'seo_title', 'meta_description', 'is_published',
        'published_at', 'reviewed_at', 'locale',
    ];

    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
            'published_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $post) {
            $post->slug = $post->slug ?: Str::slug($post->title);

            // The body is written in the admin and rendered with v-html, so
            // whatever is stored runs in every reader's browser. Cleaned here
            // rather than at render, because there is one save path and many
            // read paths.
            $post->body = app(HtmlSanitiser::class)->clean($post->body);

            // Counted from the rendered text, not the source, so markup does
            // not inflate it.
            $post->reading_minutes = max(1, (int) ceil(
                str_word_count(strip_tags((string) $post->body)) / self::WORDS_PER_MINUTE
            ));

            // Publishing without a date would put the post at the bottom of a
            // list ordered by date, where nobody would ever see it.
            if ($post->is_published && $post->published_at === null) {
                $post->published_at = now();
            }
        });
    }

    /** @param  Builder<Post>  $query */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }

    /** @param  Builder<Post>  $query */
    public function scopeForLocale(Builder $query, ?string $locale = null): Builder
    {
        return $query->where('locale', $locale ?? app()->getLocale());
    }

    public function url(): string
    {
        return url('/blog/'.$this->slug);
    }

    /**
     * The date a reader should judge this by.
     *
     * A piece checked last month is worth more than one written last month and
     * never looked at since — on a subject where the law moves, the review is
     * the more honest date to lead with.
     */
    public function lastVerifiedAt(): ?Carbon
    {
        return $this->reviewed_at ?? $this->published_at;
    }

    public function wasReviewedAfterPublishing(): bool
    {
        return $this->reviewed_at !== null
            && $this->published_at !== null
            && $this->reviewed_at->gt($this->published_at);
    }
}
