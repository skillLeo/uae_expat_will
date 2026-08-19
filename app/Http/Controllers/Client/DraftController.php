<?php

namespace App\Http\Controllers\Client;

use App\Domain\Cases\Actions\ChangeCaseStatus;
use App\Domain\Cases\Actions\RecordStageTimestamp;
use App\Domain\Cases\Enums\CaseStage;
use App\Domain\Cases\Enums\InternalStatus;
use App\Http\Controllers\Controller;
use App\Models\Consent;
use App\Models\Draft;
use App\Models\LegalCase;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Draft review, amendments and approval.
 *
 * Approval is recorded as a CONSENT with the wording hash of the draft, because
 * "the client approved it" has to mean "the client approved THIS text" if it is
 * ever questioned.
 */
class DraftController extends Controller
{
    public function __construct(
        private ChangeCaseStatus $changeStatus,
        private RecordStageTimestamp $recordStage,
    ) {}

    public function show(Request $request, LegalCase $case): Response
    {
        $this->authoriseCase($request, $case);

        $case->load('drafts.amendments');
        $allowance = (int) setting('commercial.amendment_allowance', 2);

        return Inertia::render('Client/Drafts', [
            // Named `record`: `case` is a reserved word in JavaScript and
            // cannot appear in a Vue template expression.
            'record' => ['id' => $case->id, 'reference' => $case->reference],
            'drafts' => $case->drafts->map(fn (Draft $d) => [
                'id' => $d->id,
                'version_number' => $d->version_number,
                'status' => $d->status,
                'sent_at' => $d->sent_at?->toIso8601String(),
                'approved_at' => $d->approved_at?->toIso8601String(),
                'approved' => $d->approved_by_customer,
                'url' => $d->getFirstMedia('draft')
                    ? URL::temporarySignedRoute('client.drafts.download', now()->addMinutes(15), ['draft' => $d->id])
                    : null,
                'amendments' => $d->amendments->map(fn ($a) => [
                    'body' => $a->body,
                    'status' => $a->status,
                    'within_allowance' => $a->is_within_allowance,
                    'at' => $a->created_at->toIso8601String(),
                ]),
                'amendments_used' => $d->amendmentsUsed(),
            ]),
            'allowance' => $allowance,
        ]);
    }

    public function download(Request $request, Draft $draft)
    {
        abort_unless($request->hasValidSignature(), 403);

        $media = $draft->getFirstMedia('draft');
        abort_if($media === null, 404);

        return $media->toResponse($request);
    }

    public function requestAmendment(Request $request, Draft $draft): RedirectResponse
    {
        $this->authoriseCase($request, $draft->legalCase);

        $validated = $request->validate(['body' => 'required|string|max:10000']);

        $allowance = (int) setting('commercial.amendment_allowance', 2);
        $used = $draft->amendmentsUsed();

        $draft->amendments()->create([
            'requested_by' => $request->user()->id,
            'body' => $validated['body'],
            // Beyond the allowance is still accepted — it is a commercial
            // conversation, not a technical block.
            'is_within_allowance' => $used < $allowance,
            'status' => 'open',
        ]);

        $draft->update(['status' => 'amendments_requested']);

        $this->changeStatus->execute(
            $draft->legalCase,
            $used === 0 ? InternalStatus::AmendmentRound1 : InternalStatus::AmendmentRound2,
            'Customer requested an amendment.',
        );

        return back()->with(
            'success',
            $used < $allowance
                ? 'Your amendment request has been sent to the legal team.'
                : 'Request sent. This is beyond your included allowance, so the team will confirm any fee before doing the work.',
        );
    }

    public function approve(Request $request, Draft $draft): RedirectResponse
    {
        $this->authoriseCase($request, $draft->legalCase);

        $request->validate(['confirm' => 'accepted']);

        $draft->update([
            'status' => 'approved',
            'approved_at' => now(),
            'approved_by_customer' => true,
        ]);

        // What was approved, not merely that something was.
        Consent::create([
            'case_id' => $draft->case_id,
            'user_id' => $request->user()->id,
            'type' => 'draft_approval',
            'version' => (string) $draft->version_number,
            'wording_hash' => Consent::hashWording(
                $draft->getFirstMedia('draft')?->getPath()
                    ? (string) @md5_file($draft->getFirstMedia('draft')->getPath())
                    : 'draft-'.$draft->id.'-v'.$draft->version_number,
            ),
            'accepted' => true,
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 500),
            'language' => app()->getLocale(),
            'related_reference' => $draft->legalCase->reference,
            'accepted_at' => now(),
        ]);

        $this->changeStatus->execute($draft->legalCase, InternalStatus::ApprovedByClient, 'Customer approved the final wording.');
        $this->recordStage->execute($draft->legalCase, CaseStage::FinalApproval);

        return back()->with('success', 'Your approval has been recorded. Approving the wording does not itself register the Will.');
    }

    private function authoriseCase(Request $request, LegalCase $case): void
    {
        abort_unless($case->customer?->user_id === $request->user()->id, 403);
    }
}
