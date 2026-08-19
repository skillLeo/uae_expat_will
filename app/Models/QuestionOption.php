<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuestionOption extends Model
{
    protected $fillable = [
        'question_id', 'key', 'order', 'label', 'description', 'is_exclusive', 'meta',
    ];

    protected function casts(): array
    {
        return ['is_exclusive' => 'boolean', 'meta' => 'array'];
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }
}
