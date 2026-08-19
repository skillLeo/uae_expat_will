<?php

namespace App\Domain\Cases\Actions;

use App\Domain\Cases\Enums\InternalStatus;
use App\Models\CaseStatusHistory;
use App\Models\LegalCase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Moves a case to a new internal status.
 *
 * The customer-facing group is derived, never set by hand, so the two can never
 * drift apart and an internal label can never leak into a customer response.
 * Every transition writes history.
 */
class ChangeCaseStatus
{
    public function execute(LegalCase $case, InternalStatus $to, ?string $reason = null): LegalCase
    {
        return DB::transaction(function () use ($case, $to, $reason) {
            $from = $case->internal_status;

            if ($from === $to) {
                return $case;
            }

            $case->update([
                'internal_status' => $to,
                'status' => $to->group(),
                // Reaching a capacity or influence status restricts the case.
                'is_restricted' => $case->is_restricted || $to->restrictsCase(),
                'closed_at' => in_array($to, [
                    InternalStatus::ClosedCancelled,
                    InternalStatus::ClosedDeclined,
                    InternalStatus::RegisteredAndDelivered,
                ], true) ? now() : $case->closed_at,
            ]);

            CaseStatusHistory::create([
                'case_id' => $case->id,
                'from_status' => $from->value,
                'to_status' => $to->value,
                'changed_by' => Auth::guard('admin')->id(),
                'reason' => $reason,
                'changed_at' => now(),
            ]);

            activity('case')
                ->performedOn($case)
                ->causedBy(Auth::guard('admin')->user())
                ->withProperties([
                    'reference' => $case->reference,
                    'from' => $from->value,
                    'to' => $to->value,
                ])
                ->log('Case status changed');

            return $case->fresh();
        });
    }
}
