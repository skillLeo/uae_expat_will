<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Cases\Actions\ChangeCaseStatus;
use App\Domain\Cases\Actions\RecordStageTimestamp;
use App\Domain\Cases\Enums\CaseStage;
use App\Domain\Cases\Enums\InternalStatus;
use App\Domain\Notifications\Enums\NotificationChannel;
use App\Domain\Notifications\Services\NotificationDispatcher;
use App\Http\Controllers\Controller;
use App\Models\Draft;
use App\Models\DraftAmendment;
use App\Models\LegalCase;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;

/**
 * The Summit side of the draft loop.
 *
 * Uploading a draft is what starts everything the customer sees: the draft
 * screen, the amendment allowance, and eventually the approval that records a
 * consent. Sending one also writes the first-draft stage timestamp, which is
 * what moves a refund from band B to band C — so it is deliberately an explicit
 * act, never a side effect of uploading a file.
 */
class DraftController extends Controller
{
    public function __construct(
        private ChangeCaseStatus $changeStatus,
        private RecordStageTimestamp $recordStage,
        private NotificationDispatcher $dispatcher,
    ) {}

    public function store(Request $request, LegalCase $case): RedirectResponse
    {
        $request->validate([
            'file' => 'required|file|max:20480|mimes:pdf,doc,docx',
        ]);

        DB::transaction(function () use ($request, $case) {
            $draft = $case->drafts()->create([
                'version_number' => ((int) $case->drafts()->max('version_number')) + 1,
                'status' => 'draft',
            ]);

            $draft->addMediaFromRequest('file')->toMediaCollection('draft');

            activity('drafts')
                ->performedOn($draft)
                ->causedBy($request->user('admin'))
                // The filename is not logged: a draft's name can carry matter detail.
                ->withProperties(['case' => $case->reference, 'version' => $draft->version_number])
                ->log('Draft uploaded');
        });

        return back()->with('success', 'Draft uploaded. It is not visible to the customer until you send it.');
    }

    /**
     * Releases a draft to the customer.
     *
     * This is the act that writes the first-draft stage timestamp, so it is
     * kept separate from uploading. A file sitting in the system is not the
     * same as a draft the client has been given.
     */
    public function send(Request $request, Draft $draft): RedirectResponse
    {
        if ($draft->getFirstMedia('draft') === null) {
            return back()->with('error', 'There is no file on this draft to send.');
        }

        DB::transaction(function () use ($draft, $request) {
            $case = $draft->legalCase;

            $draft->update(['status' => 'sent', 'sent_at' => now()]);

            $this->recordStage->execute($case, CaseStage::FirstDraftDelivered);
            $this->changeStatus->execute($case, InternalStatus::DraftSent, 'Draft released to the customer.');

            if ($email = $case->customer?->email) {
                $this->dispatcher->send(
                    'draft_ready',
                    NotificationChannel::Email,
                    $email,
                    [
                        'first_name' => $case->customer?->firstName() ?? 'there',
                        'reference' => $case->reference,
                    ],
                    $case,
                );
            }

            activity('drafts')
                ->performedOn($draft)
                ->causedBy($request->user('admin'))
                ->withProperties(['case' => $case->reference, 'version' => $draft->version_number])
                ->log('Draft sent to the customer');
        });

        return back()->with('success', 'Draft sent. The customer has been emailed and the first-draft stage is recorded.');
    }

    public function download(Request $request, Draft $draft)
    {
        // Staff downloads are signed too, so a copied URL from a screen share
        // stops working rather than becoming a permanent handle on the file.
        abort_unless($request->hasValidSignature(), 403);

        $media = $draft->getFirstMedia('draft');
        abort_if($media === null, 404);

        activity('drafts')
            ->performedOn($draft)
            ->causedBy($request->user('admin'))
            ->log('Draft downloaded');

        return $media->toResponse($request);
    }

    public function resolveAmendment(Request $request, DraftAmendment $amendment): RedirectResponse
    {
        $amendment->update(['status' => 'resolved', 'resolved_at' => now()]);

        activity('drafts')
            ->performedOn($amendment->draft)
            ->causedBy($request->user('admin'))
            ->log('Amendment marked resolved');

        return back()->with('success', 'Amendment marked resolved.');
    }

    /** Signed, short-lived links for the case detail screen. */
    public static function signedUrl(Draft $draft): ?string
    {
        return $draft->getFirstMedia('draft')
            ? URL::temporarySignedRoute('admin.drafts.download', now()->addMinutes(15), ['draft' => $draft->id])
            : null;
    }
}
