<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuestionnaireDeclaration extends Model
{
    protected $fillable = ['questionnaire_version_id', 'order', 'text', 'is_required'];

    protected function casts(): array
    {
        return ['is_required' => 'boolean'];
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(QuestionnaireVersion::class, 'questionnaire_version_id');
    }

    /** The hash recorded on the consent, proving WHAT wording was agreed. */
    public function wordingHash(): string
    {
        return hash('sha256', $this->text);
    }
}
