<?php

namespace App\Models;

use App\Models\Concerns\RecordsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class QuestionnaireVersion extends Model
{
    use RecordsActivity, SoftDeletes;

    protected $fillable = [
        'questionnaire_id', 'version_number', 'status',
        'published_at', 'published_by', 'notes',
    ];

    protected function casts(): array
    {
        return ['published_at' => 'datetime'];
    }

    public function questionnaire(): BelongsTo
    {
        return $this->belongsTo(Questionnaire::class);
    }

    public function questions(): HasMany
    {
        return $this->hasMany(Question::class)->orderBy('order');
    }

    public function routingRules(): HasMany
    {
        return $this->hasMany(RoutingRule::class)->orderBy('priority');
    }

    public function declarations(): HasMany
    {
        return $this->hasMany(QuestionnaireDeclaration::class)->orderBy('order');
    }

    public function resultScreens(): HasMany
    {
        return $this->hasMany(QuestionnaireResultScreen::class);
    }

    public function publisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }

    public function assessments(): HasMany
    {
        return $this->hasMany(Assessment::class);
    }

    public function isPublished(): bool
    {
        return $this->status === 'published';
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    /** Everything the engine needs, in one round trip. */
    public function loadForEngine(): self
    {
        return $this->load([
            'questions.options',
            'questions.conditions.dependsOnQuestion',
            'routingRules.conditions.question',
        ]);
    }
}
