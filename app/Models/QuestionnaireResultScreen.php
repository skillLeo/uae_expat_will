<?php

namespace App\Models;

use App\Domain\Assessment\Enums\Outcome;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuestionnaireResultScreen extends Model
{
    protected $fillable = [
        'questionnaire_version_id', 'outcome', 'heading', 'body',
        'primary_action_label', 'secondary_action_label', 'extra',
    ];

    protected function casts(): array
    {
        return ['outcome' => Outcome::class, 'extra' => 'array'];
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(QuestionnaireVersion::class, 'questionnaire_version_id');
    }
}
