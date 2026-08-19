<?php

namespace App\Models;

use App\Domain\Assessment\Enums\QuestionType;
use App\Models\Concerns\RecordsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Question extends Model
{
    use RecordsActivity, SoftDeletes;

    protected $fillable = [
        'questionnaire_version_id', 'key', 'order', 'type', 'prompt', 'help_text',
        'privacy_note', 'security_note', 'is_required', 'is_sensitive',
        'section_key', 'placeholder', 'min', 'max', 'meta',
    ];

    protected function casts(): array
    {
        return [
            'type' => QuestionType::class,
            'is_required' => 'boolean',
            'is_sensitive' => 'boolean',
            'meta' => 'array',
        ];
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(QuestionnaireVersion::class, 'questionnaire_version_id');
    }

    public function options(): HasMany
    {
        return $this->hasMany(QuestionOption::class)->orderBy('order');
    }

    public function conditions(): HasMany
    {
        return $this->hasMany(QuestionCondition::class);
    }

    public function answers(): HasMany
    {
        return $this->hasMany(AssessmentAnswer::class);
    }

    /** The exclusive option, if this question has one ("None of these"). */
    public function exclusiveOption(): ?QuestionOption
    {
        return $this->options->firstWhere('is_exclusive', true);
    }

    /** Turn a stored answer into something a human can read in the case detail. */
    public function labelForAnswer(mixed $value): string
    {
        if ($value === null || $value === '' || $value === []) {
            return '—';
        }

        if (! $this->type->hasOptions()) {
            return is_array($value) ? implode(', ', $value) : (string) $value;
        }

        $keys = is_array($value) ? $value : [$value];

        $labels = $this->options
            ->whereIn('key', $keys)
            ->pluck('label')
            ->all();

        return $labels === [] ? (string) reset($keys) : implode(' · ', $labels);
    }
}
