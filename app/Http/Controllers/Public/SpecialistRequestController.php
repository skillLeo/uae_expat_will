<?php

namespace App\Http\Controllers\Public;

use App\Domain\Cases\Actions\CreateSpecialistRequest;
use App\Domain\Cases\Enums\RequestType;
use App\Http\Controllers\Controller;
use App\Models\LegalCase;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The specialist legal review request.
 *
 * For the two enquiries that are not Will preparation: amending an existing
 * Will, and administering an estate after a death. Both used to be answered
 * with a page saying the online Will service was not available. Summit's
 * instruction of 26 August is that the platform must never say that to them.
 *
 * All client-facing copy here is transcribed from that handoff and must not be
 * reworded in passing.
 */
class SpecialistRequestController extends Controller
{
    private const CASE_KEY = 'specialist_request_case';

    private const CONSENT_VERSION = '2026-08-26';

    private const CONSENT_WORDING = 'I accept the Privacy Policy and consent to be contacted about this request.';

    public function show(Request $request, string $service): Response|RedirectResponse
    {
        $type = RequestType::tryFrom($service);

        if ($type === null || ! $type->isDirectSpecialistRequest()) {
            return redirect()->route('assessment.show');
        }

        $case = $this->resolveCase($request);

        return Inertia::render('Specialist/Request', [
            'service' => ['value' => $type->value, 'label' => $type->label()],
            'serviceNote' => $type->requestFormNote(),
            'step' => $case ? 2 : 1,
            'reference' => $case?->reference,
            'countries' => config('countries.list'),
            'consent' => ['version' => self::CONSENT_VERSION, 'wording' => self::CONSENT_WORDING],
            'copy' => [
                'eyebrow' => 'Specialist legal request',
                'heading' => 'Your Request Requires Specialist Legal Review',
                'body' => 'This service is handled directly by the legal team at Summit Legal Consultancy. '
                    .'Please provide your contact details and some brief information about your request. Our team '
                    .'will review it and contact you to discuss the appropriate service, fees and next steps.',
                'primary' => 'Submit My Request',
                'secondary' => 'Contact Us on WhatsApp',
                'reassurance' => 'No payment is required to submit this request.',
            ],
        ]);
    }

    /**
     * Step one. The lead is saved here, before anything else is asked, so a
     * person who abandons the second step is still on the dashboard tomorrow.
     */
    public function contact(Request $request, string $service, CreateSpecialistRequest $action): RedirectResponse
    {
        $type = RequestType::tryFrom($service);

        if ($type === null || ! $type->isDirectSpecialistRequest()) {
            return redirect()->route('assessment.show');
        }

        if ($this->resolveCase($request)) {
            return back();
        }

        $validated = $request->validate([
            'full_name' => 'required|string|max:120',
            'email' => 'required|email:rfc|max:190',
            'phone' => 'required|string|max:40',
            'country_of_residence' => ['required', 'string', Rule::in(array_keys(config('countries.list')))],
            'preferred_contact_method' => 'required|string|in:email,telephone,whatsapp',
        ], [], [
            'full_name' => 'name',
            'email' => 'email address',
            'phone' => 'mobile or WhatsApp number',
            'country_of_residence' => 'country of residence',
            'preferred_contact_method' => 'preferred contact method',
        ]);

        $case = $action->captureContact($type, $validated);

        $request->session()->put(self::CASE_KEY, $case->id);

        return back();
    }

    /** Step two. Updates the same case — never creates a second one. */
    public function submit(Request $request, string $service, CreateSpecialistRequest $action): RedirectResponse
    {
        $case = $this->resolveCase($request);

        if ($case === null) {
            return back()->with('error', 'Please provide your contact details first.');
        }

        $validated = $request->validate([
            'brief_description' => 'required|string|min:10|max:5000',
            'consent' => 'accepted',
        ], [
            'consent.accepted' => 'Please confirm you accept the Privacy Policy and consent to be contacted.',
        ], [
            'brief_description' => 'description of your request',
        ]);

        $action->completeRequest($case, [
            'brief_description' => $validated['brief_description'],
            'consent_version' => self::CONSENT_VERSION,
            'consent_wording' => self::CONSENT_WORDING,
        ], $request);

        $request->session()->forget(self::CASE_KEY);
        $request->session()->put('specialist_request_reference', $case->reference);

        return redirect()->route('specialist.received');
    }

    public function received(Request $request): Response|RedirectResponse
    {
        $reference = $request->session()->get('specialist_request_reference');

        if ($reference === null) {
            return redirect()->route('pages.home');
        }

        return Inertia::render('Specialist/Received', [
            'reference' => $reference,
            'copy' => [
                'eyebrow' => 'Request received',
                'heading' => 'Your Request Has Been Received',
                'body' => 'Thank you. Your request has been sent securely to the legal team at Summit Legal '
                    .'Consultancy. We will review the information provided and contact you within one business '
                    .'day to discuss the appropriate service and next steps.',
                'primary' => 'Return to Homepage',
                'secondary' => 'Contact Us on WhatsApp',
                'payment' => 'No payment has been taken.',
                'notice_heading' => 'Submission of this request does not confirm acceptance of the matter or '
                    .'create a professional engagement.',
                'notice_body' => 'The service scope, fees and applicable terms must be confirmed before work begins.',
            ],
        ]);
    }

    private function resolveCase(Request $request): ?LegalCase
    {
        $id = $request->session()->get(self::CASE_KEY);

        return $id ? LegalCase::find($id) : null;
    }
}
