<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Cases\Actions\AssignCase;
use App\Domain\Cases\Actions\ChangeCaseStatus;
use App\Domain\Cases\Actions\IssueMagicLink;
use App\Domain\Cases\Actions\RecordStageTimestamp;
use App\Domain\Cases\Enums\CaseStage;
use App\Domain\Cases\Enums\InternalStatus;
use App\Http\Controllers\Controller;
use App\Models\CaseContact;
use App\Models\CaseNote;
use App\Models\LegalCase;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CaseActionController extends Controller
{
    public function assign(Request $request, LegalCase $case, AssignCase $action): RedirectResponse
    {
        $validated = $request->validate([
            'assigned_to' => 'nullable|exists:users,id',
            // A reassignment must say why. A first assignment need not.
            'reason' => [$case->assigned_to ? 'required' : 'nullable', 'string', 'max:500'],
        ]);

        $action->execute(
            $case,
            $validated['assigned_to'] ? User::find($validated['assigned_to']) : null,
            $validated['reason'] ?? null,
        );

        return back()->with('success', 'Case assignment updated.');
    }

    public function changeStatus(Request $request, LegalCase $case, ChangeCaseStatus $action): RedirectResponse
    {
        $validated = $request->validate([
            'internal_status' => 'required|string|in:'.collect(InternalStatus::cases())->pluck('value')->implode(','),
            'reason' => 'nullable|string|max:500',
        ]);

        $action->execute($case, InternalStatus::from($validated['internal_status']), $validated['reason'] ?? null);

        return back()->with('success', 'Status updated.');
    }

    public function addNote(Request $request, LegalCase $case): RedirectResponse
    {
        $validated = $request->validate([
            'body' => 'required|string|max:10000',
            'is_internal' => 'boolean',
        ]);

        CaseNote::create($validated + [
            'case_id' => $case->id,
            'user_id' => $request->user('admin')->id,
        ]);

        $case->increment('notes_count');

        return back()->with('success', 'Note added.');
    }

    public function logContact(Request $request, LegalCase $case): RedirectResponse
    {
        $validated = $request->validate([
            'channel' => 'required|string|in:email,whatsapp,phone,meeting',
            'direction' => 'required|string|in:inbound,outbound',
            'summary' => 'nullable|string|max:2000',
            'occurred_at' => 'nullable|date',
        ]);

        CaseContact::create($validated + [
            'case_id' => $case->id,
            'user_id' => $request->user('admin')->id,
            'occurred_at' => $validated['occurred_at'] ?? now(),
        ]);

        // Logging contact clears the countdown — that is what the countdown was
        // measuring.
        $case->update(['last_contact_at' => now(), 'countdown_due_at' => null]);

        return back()->with('success', 'Contact logged.');
    }

    public function recordStage(Request $request, LegalCase $case, RecordStageTimestamp $action): RedirectResponse
    {
        $validated = $request->validate([
            'stage' => 'required|string|in:'.collect(CaseStage::cases())->pluck('value')->implode(','),
        ]);

        $action->execute($case, CaseStage::from($validated['stage']));

        return back()->with('success', 'Stage recorded. This affects the refund band.');
    }

    public function issueMagicLink(LegalCase $case, IssueMagicLink $action): RedirectResponse
    {
        ['url' => $url] = $action->execute($case);

        // The raw token exists only here and in the email. It is deliberately
        // NOT flashed into the session or logged.
        return back()->with('success', 'A new single-use link has been issued and emailed. Any previous link is now revoked.');
    }

    public function setCountdown(Request $request, LegalCase $case): RedirectResponse
    {
        $validated = $request->validate(['hours' => 'required|integer|min:1|max:720']);

        $case->update(['countdown_due_at' => now()->addHours($validated['hours'])]);

        return back()->with('success', 'Countdown set.');
    }
}
