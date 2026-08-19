<?php

namespace App\Domain\Cases\Actions;

use App\Models\LegalCase;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

/**
 * Assigns or reassigns a case, with a stated reason.
 *
 * The reason is required on a REASSIGNMENT because that is the one that needs
 * explaining later: who moved this, and why.
 */
class AssignCase
{
    public function execute(LegalCase $case, ?User $assignee, ?string $reason = null): LegalCase
    {
        $previous = $case->assignee;

        $case->update(['assigned_to' => $assignee?->id]);

        activity('case')
            ->performedOn($case)
            ->causedBy(Auth::guard('admin')->user())
            ->withProperties([
                'reference' => $case->reference,
                'from' => $previous?->name,
                'to' => $assignee?->name,
                'reason' => $reason,
            ])
            ->log($previous ? 'Case reassigned' : 'Case assigned');

        return $case->fresh();
    }
}
