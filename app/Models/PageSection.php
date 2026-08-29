<?php

namespace App\Models;

use App\Domain\Content\Services\HtmlSanitiser;
use App\Models\Concerns\RecordsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PageSection extends Model
{
    protected static function booted(): void
    {
        // Section bodies reach the page through v-html, exactly as an article
        // body does, and are editable under the same content.edit permission.
        static::saving(function (self $section) {
            if (is_string($section->body)) {
                $section->body = app(HtmlSanitiser::class)->clean($section->body);
            }
        });
    }

    use RecordsActivity;

    protected $fillable = [
        'page_id', 'key', 'order', 'type', 'heading', 'subheading',
        'body', 'items', 'settings', 'locale',
    ];

    protected function casts(): array
    {
        return ['items' => 'array', 'settings' => 'array'];
    }

    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }
}
