<?php

namespace App\Models;

use App\Domain\Assessment\Enums\Outcome;
use App\Models\Concerns\RecordsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class RoutingRule extends Model
{
    use RecordsActivity, SoftDeletes;

    protected $fillable = [
        'questionnaire_version_id', 'name', 'priority', 'outcome', 'outcome_detail',
        'flag_key', 'reminder_key', 'route_mark_key', 'is_terminal', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'outcome' => Outcome::class,
            'is_terminal' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(QuestionnaireVersion::class, 'questionnaire_version_id');
    }

    public function conditions(): HasMany
    {
        return $this->hasMany(RoutingRuleCondition::class)->orderBy('group_index');
    }

    /**
     * Render the rule as a sentence for the admin rule builder.
     * Groups OR together; conditions inside a group AND together.
     */
    public function describe(): string
    {
        $groups = $this->conditions->groupBy('group_index');

        $clauses = $groups->map(
            fn ($group) => $group->map(fn (RoutingRuleCondition $c) => $c->describe())->implode(' AND ')
        )->implode(' OR ');

        return $clauses === ''
            ? sprintf('ALWAYS → %s', $this->outcome->label())
            : sprintf('IF %s THEN %s', $clauses, $this->outcome->label());
    }
}
