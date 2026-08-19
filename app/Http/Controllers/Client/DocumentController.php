<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\LegalCase;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Customer document upload.
 *
 * Everything lands on the PRIVATE disk and is served only through signed,
 * expiring URLs. Nothing is ever written somewhere web-reachable, so a guessed
 * path yields nothing.
 */
class DocumentController extends Controller
{
    private const CATEGORIES = [
        'passport' => 'Passport',
        'emirates_id' => 'Emirates ID',
        'title_deed' => 'Title deed',
        'existing_will' => 'An existing Will',
        'other' => 'Something else',
    ];

    public function index(Request $request, LegalCase $case): Response
    {
        $this->authoriseCase($request, $case);

        return Inertia::render('Client/Documents', [
            // Named `record`: `case` is a reserved word in JavaScript and
            // cannot appear in a Vue template expression.
            'record' => ['id' => $case->id, 'reference' => $case->reference],
            'categories' => collect(self::CATEGORIES)->map(fn ($label, $key) => ['value' => $key, 'label' => $label])->values(),
            'documents' => $case->documents()->with('media')->latest()->get()
                ->map(fn (Document $d) => [
                    'id' => $d->id,
                    'category' => self::CATEGORIES[$d->category] ?? $d->category,
                    'status' => $d->status,
                    'review_note' => $d->review_note,
                    'filename' => $d->file()?->file_name,
                    'size' => $d->file()?->size,
                    'uploaded_at' => $d->created_at->toIso8601String(),
                    // Signed and short-lived. Never a permanent URL.
                    'url' => URL::temporarySignedRoute('client.documents.download', now()->addMinutes(10), ['document' => $d->id]),
                ]),
            'uploadEnabled' => feature('document_upload_enabled'),
        ]);
    }

    public function store(Request $request, LegalCase $case): RedirectResponse
    {
        $this->authoriseCase($request, $case);
        abort_unless(feature('document_upload_enabled'), 403);

        $validated = $request->validate([
            'category' => 'required|string|in:'.implode(',', array_keys(self::CATEGORIES)),
            'file' => 'required|file|max:10240|mimes:pdf,jpg,jpeg,png,heic,webp,doc,docx',
        ]);

        $document = $case->documents()->create([
            'uploaded_by' => $request->user()->id,
            'category' => $validated['category'],
            'status' => 'pending',
        ]);

        $document->addMediaFromRequest('file')->toMediaCollection('files');

        activity('documents')
            ->performedOn($document)
            ->causedBy($request->user())
            // The FILENAME is not logged: "divorce-decree.pdf" is matter detail.
            ->withProperties(['case' => $case->reference, 'category' => $validated['category']])
            ->log('Document uploaded');

        return back()->with('success', 'Document uploaded.');
    }

    public function download(Request $request, Document $document): StreamedResponse
    {
        // The signature is the authorisation. It is generated per viewer and
        // expires, so a forwarded URL stops working.
        abort_unless($request->hasValidSignature(), 403);

        $media = $document->file();
        abort_if($media === null, 404);

        return $media->toResponse($request);
    }

    public function destroy(Request $request, Document $document): RedirectResponse
    {
        $this->authoriseCase($request, $document->legalCase);
        abort_unless($document->status === 'pending', 403, 'A reviewed document cannot be removed.');

        $document->delete();

        return back()->with('success', 'Document removed.');
    }

    private function authoriseCase(Request $request, LegalCase $case): void
    {
        abort_unless($case->customer?->user_id === $request->user()->id, 403);
    }
}
