<?php

namespace App\Domain\Cases\Actions;

use App\Domain\Cases\Enums\CaseStatus;
use App\Domain\Cases\Enums\InternalStatus;
use App\Domain\Cases\Enums\RequestType;
use App\Domain\Cases\Services\ReferenceGenerator;
use App\Models\Consent;
use App\Models\Customer;
use App\Models\LegalCase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * A specialist legal review request.
 *
 * Two steps, and the split is the whole point. Step one writes the contact
 * details and opens the case immediately; step two adds the narrative and the
 * consent to the SAME case. Summit's instruction is explicit — "save the
 * contact information before asking for additional details so the lead is
 * protected if the user does not finish the second step" — and equally explicit
 * that step two must not create a second case.
 *
 * Somebody who types their name and then closes the tab is still a person who
 * wanted to buy something. They appear on the dashboard as "contact captured,
 * details incomplete" and can be phoned.
 */
class CreateSpecialistRequest
{
    public function __construct(private ReferenceGenerator $references) {}

    /** @param array<string, mixed> $data */
    public function captureContact(RequestType $type, array $data): LegalCase
    {
        return DB::transaction(function () use ($type, $data) {
            $customer = Customer::create([
                'full_name' => $data['full_name'],
                'email' => $data['email'],
                'phone' => $data['phone'],
                'country_of_residence' => $data['country_of_residence'],
                'preferred_contact_method' => $data['preferred_contact_method'],
            ]);

            return LegalCase::create([
                'reference' => $this->references->generate(),
                'customer_id' => $customer->id,
                'request_type' => $type,
                'service_type' => $type->value,
                'status' => CaseStatus::AssessmentCompleted,
                'internal_status' => InternalStatus::ContactCapturedIncomplete,
                // No quote. Nothing here is priced until a human has looked.
                'quoted_amount' => null,
            ]);
        });
    }

    /** @param array<string, mixed> $data */
    public function completeRequest(LegalCase $case, array $data, Request $request): LegalCase
    {
        return DB::transaction(function () use ($case, $data, $request) {
            $case->update([
                'brief_description' => $data['brief_description'],
                'internal_status' => InternalStatus::NewLegalReviewRequired,
                'status' => InternalStatus::NewLegalReviewRequired->group(),
            ]);

            // The consent is evidence, so it records the wording that was
            // agreed and not merely that a box was ticked.
            Consent::create([
                'case_id' => $case->id,
                'type' => 'specialist_request',
                'version' => (string) $data['consent_version'],
                'wording_hash' => Consent::hashWording($data['consent_wording']),
                'accepted' => true,
                'ip_address' => $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 500),
                'language' => app()->getLocale(),
                'accepted_at' => now(),
            ]);

            activity('case')
                ->performedOn($case)
                ->withProperties([
                    'reference' => $case->reference,
                    'request_type' => $case->request_type->value,
                ])
                ->log('Specialist request submitted');

            return $case->fresh();
        });
    }
}
