<?php

namespace App\Models;

use App\Domain\Assessment\Enums\ConditionOperator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoutingRuleCondition extends Model
{
    protected $fillable = [
        'routing_rule_id', 'question_id', 'operator', 'value', 'group_index', 'group_operator',
    ];

    protected function casts(): array
    {
        return ['operator' => ConditionOperator::class, 'value' => 'array'];
    }

    public function rule(): BelongsTo
    {
        return $this->belongsTo(RoutingRule::class, 'routing_rule_id');
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }

    public function describe(): string
    {
        $value = is_array($this->value) ? implode(' / ', $this->value) : (string) $this->value;

        return trim(sprintf(
            '%s %s %s',
            $this->question?->key ?? '?',
            $this->operator->label(),
            $value,
        ));
    }
}
