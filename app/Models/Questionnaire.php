<?php

namespace App\Models;

use App\Models\Concerns\RecordsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Questionnaire extends Model
{
    use RecordsActivity, SoftDeletes;

    protected $fillable = ['key', 'name', 'type', 'description'];

    public function versions(): HasMany
    {
        return $this->hasMany(QuestionnaireVersion::class);
    }

    /**
     * The one published version. Only ever one per questionnaire — enforced by
     * PublishQuestionnaireVersion, which archives the incumbent in the same
     * transaction as it publishes the successor.
     */
    public function publishedVersion(): ?QuestionnaireVersion
    {
        return $this->versions()->where('status', 'published')->latest('version_number')->first();
    }

    public function draftVersion(): ?QuestionnaireVersion
    {
        return $this->versions()->where('status', 'draft')->latest('version_number')->first();
    }

    public static function screening(): ?self
    {
        return static::where('key', 'screening')->first();
    }
}
