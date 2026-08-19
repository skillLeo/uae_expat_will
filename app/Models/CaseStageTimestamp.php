<?php

namespace App\Models;

use App\Domain\Cases\Enums\CaseStage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** The evidence the refund engine reasons from. Written once, never edited. */
class CaseStageTimestamp extends Model
{
    protected $fillable = ['case_id', 'stage', 'occurred_at', 'recorded_by'];

    protected function casts(): array
    {
        return ['stage' => CaseStage::class, 'occurred_at' => 'datetime'];
    }

    public function legalCase(): BelongsTo
    {
        return $this->belongsTo(LegalCase::class, 'case_id');
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
