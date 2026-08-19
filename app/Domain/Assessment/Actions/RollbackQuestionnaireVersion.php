<?php

namespace App\Domain\Assessment\Actions;

use App\Models\QuestionnaireVersion;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Rolls back to a previous version by re-publishing it.
 *
 * Nothing is deleted. The version being replaced is archived, not destroyed, so
 * a rollback is itself reversible and the history stays complete.
 */
class RollbackQuestionnaireVersion
{
    public function execute(QuestionnaireVersion $target): QuestionnaireVersion
    {
        return DB::transaction(function () use ($target) {
            $current = $target->questionnaire->publishedVersion();

            $current?->update(['status' => 'archived']);

            $target->update([
                'status' => 'published',
                'published_at' => now(),
                'published_by' => Auth::guard('admin')->id(),
            ]);

            activity('questionnaire')
                ->performedOn($target)
                ->causedBy(Auth::guard('admin')->user())
                ->withProperties([
                    'rolled_back_to' => $target->version_number,
                    'from' => $current?->version_number,
                ])
                ->log('Questionnaire rolled back');

            return $target->fresh();
        });
    }
}
