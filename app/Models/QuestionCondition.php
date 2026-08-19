<?php

namespace App\Models;

use App\Domain\Assessment\Enums\ConditionAction;
use App\Domain\Assessment\Enums\ConditionOperator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuestionCondition extends Model
{
    protected $fillable = [
        'question_id', 'depends_on_question_id', 'operator',
        'value', 'action', 'target_section_key',
    ];

    protected function casts(): array
    {
        return [
            'operator' => ConditionOperator::class,
            'action' => ConditionAction::class,
            'value' => 'array',
        ];
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }

    public function dependsOnQuestion(): BelongsTo
    {
        return $this->belongsTo(Question::class, 'depends_on_question_id');
    }

    /** Readable in the admin rule builder: "shown when Q4 is In the UAE". */
    public function describe(): string
    {
        $q = $this->dependsOnQuestion;
        $value = is_array($this->value) ? implode(' or ', $this->value) : (string) $this->value;

        return sprintf(
            '%s when %s %s %s',
            $this->action->label(),
            $q?->key ?? '?',
            $this->operator->label(),
            $value,
        );
    }
}
