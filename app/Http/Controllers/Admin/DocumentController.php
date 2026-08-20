<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\LegalCase;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

/**
 * Staff review of customer uploads.
 *
 * Accepting or rejecting is recorded with a note the customer can read, because
 * "rejected" on its own tells them nothing about what to do next.
 */
class DocumentController extends Controller
{
    public function review(Request $request, Document $document): RedirectResponse
    {
        $validated = $request->validate([
            'status' => 'required|string|in:accepted,rejected,pending',
            'review_note' => 'nullable|string|max:1000',
        ]);

        // A rejection without a reason is unactionable for the customer.
        if ($validated['status'] === 'rejected' && blank($validated['review_note'] ?? null)) {
            return back()->with('error', 'Give a reason when rejecting a document — the customer sees it and needs to know what to send instead.');
        }

        $document->update($validated);

        activity('documents')
            ->performedOn($document)
            ->causedBy($request->user('admin'))
            // Category and outcome only. The filename can carry matter detail.
            ->withProperties([
                'case' => $document->legalCase->reference,
                'category' => $document->category,
                'status' => $validated['status'],
            ])
            ->log('Document reviewed');

        return back()->with('success', 'Document marked '.$validated['status'].'.');
    }

    public function download(Request $request, Document $document)
    {
        abort_unless($request->hasValidSignature(), 403);

        $media = $document->file();
        abort_if($media === null, 404);

        activity('documents')
            ->performedOn($document)
            ->causedBy($request->user('admin'))
            ->withProperties(['case' => $document->legalCase->reference])
            ->log('Document opened');

        return $media->toResponse($request);
    }

    public static function signedUrl(Document $document): ?string
    {
        return $document->file()
            ? URL::temporarySignedRoute('admin.documents.download', now()->addMinutes(10), ['document' => $document->id])
            : null;
    }

    public function store(Request $request, LegalCase $case): RedirectResponse
    {
        $validated = $request->validate([
            'category' => 'required|string|max:40',
            'file' => 'required|file|max:20480|mimes:pdf,jpg,jpeg,png,heic,webp,doc,docx',
        ]);

        $document = $case->documents()->create([
            'uploaded_by' => $request->user('admin')->id,
            'category' => $validated['category'],
            'status' => 'accepted',
        ]);

        $document->addMediaFromRequest('file')->toMediaCollection('files');

        return back()->with('success', 'Document added to the matter.');
    }
}
