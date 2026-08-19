<?php

namespace App\Domain\Cases\Actions;

use App\Domain\Cases\Enums\CaseStage;
use App\Models\CaseStageTimestamp;
use App\Models\LegalCase;
use Illuminate\Support\Facades\Auth;

/**
 * Records that a case reached a stage.
 *
 * Written once and never updated. These rows are the sole evidence the refund
 * engine reasons from, so a stage that moved would change a refund that has
 * already been justified to a client.
 */
class RecordStageTimestamp
{
    public function execute(LegalCase $case, CaseStage $stage, ?\DateTimeInterface $at = null): CaseStageTimestamp
    {
        return CaseStageTimestamp::firstOrCreate(
            ['case_id' => $case->id, 'stage' => $stage],
            ['occurred_at' => $at ?? now(), 'recorded_by' => Auth::guard('admin')->id()],
        );
    }
}
