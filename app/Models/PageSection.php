<?php

namespace App\Models;

use App\Models\Concerns\RecordsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PageSection extends Model
{
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
