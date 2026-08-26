<?php

namespace App\Domain\Cases\Actions;

use App\Domain\Assessment\DTOs\RoutingResult;
use App\Domain\Cases\Enums\InternalStatus;
use App\Domain\Cases\Enums\RequestType;
use App\Domain\Cases\Services\ReferenceGenerator;
use App\Domain\Notifications\Actions\SendInternalNewLeadAlert;
use App\Models\Assessment;
use App\Models\CaseStatusHistory;
use App\Models\Customer;
use App\Models\LegalCase;
use Illuminate\Support\Facades\DB;

class CreateCaseFromAssessment
{
    public function __construct(
        private ReferenceGenerator $references,
        private SendInternalNewLeadAlert $alert,
    ) {}

    /** @param array<string, mixed> $contact */
    public function execute(Assessment $assessment, RoutingResult $result, array $contact = []): LegalCase
    {
        return DB::transaction(function () use ($assessment, $result, $contact) {
            $customer = $this->customer($assessment, $contact);
            $requestType = RequestType::fromServiceAnswer($assessment->answerSet()->get('q1'));

            // A DIFC request is a review ticket, not a held matter. It reaches
            // this point having answered every question, and the team needs to
            // see at a glance that it is waiting on a quotation rather than on
            // a compliance decision.
            $internal = $requestType === RequestType::DifcWill
                ? InternalStatus::DifcLegalReviewRequired
                : $this->internalStatus($result);

            $case = new LegalCase([
                'reference' => $this->references->generate(),
                'customer_id' => $customer?->id,
                'assessment_id' => $assessment->id,
                'pathway' => $result->routeMarks[0] ?? ($result->allowsPayment() ? 'standard_online' : 'review'),
                'status' => $internal->group(),
                'internal_status' => $internal,
                'is_restricted' => $result->isRestricted(),
                'request_type' => $requestType,
                'service_type' => $requestType->value,
                // Nothing is priced until a human has looked at a DIFC matter,
                // and two Wills carry their own fee rather than twice one fee.
                'quoted_amount' => match (true) {
                    $requestType->requiresQuotationFirst() => null,
                    ! $result->allowsPayment() => null,
                    $requestType === RequestType::MirrorWills => (float) setting('commercial.mirror_fee', 2999),
                    default => (float) setting('commercial.standard_fee', 1999),
                },
                'currency' => setting('commercial.currency', 'AED'),
                // First contact target: 4 working hours per the internal alert spec.
                'countdown_due_at' => now()->addHours(4),
            ]);

            if ($result->isRestricted()) {
                // Stored encrypted, and readable only with cases.view_restricted.
                $case->setRestrictedReason(
                    'Capacity or undue influence indicated at Q15B. Handle under special '
                    .'confidentiality: the answer must not be disclosed to any person who '
                    .'may be influencing the customer.'
                );
            }

            $case->save();

            CaseStatusHistory::create([
                'case_id' => $case->id,
                'from_status' => null,
                'to_status' => $internal->value,
                'reason' => 'Assessment completed.',
                'changed_at' => now(),
            ]);

            // Notifies Summit on both administrator numbers plus email. The alert
            // carries the reference and the outcome bucket and NOTHING else — for
            // a restricted case it says only that a matter needs immediate legal
            // attention, never why.
            $this->alert->execute($case, $result);

            return $case;
        });
    }

    /** @param array<string, mixed> $contact */
    private function customer(Assessment $assessment, array $contact): ?Customer
    {
        if (($contact['email'] ?? null) === null) {
            return null;
        }

        return Customer::create([
            'full_name' => $contact['full_name'] ?? 'Not provided',
            'email' => $contact['email'],
            'phone' => $contact['phone'] ?? null,
            'nationality' => $contact['nationality'] ?? $assessment->answerSet()->get('q3'),
            'country_of_residence' => $contact['country_of_residence'] ?? null,
            'emirate' => $assessment->answerSet()->get('q4a'),
            'preferred_contact_method' => $contact['preferred_contact_method'] ?? null,
            'language_support' => $assessment->answerSet()->get('q16'),
        ]);
    }

    private function internalStatus(RoutingResult $result): InternalStatus
    {
        return match (true) {
            $result->isRestricted() => InternalStatus::HeldCapacityOrInfluence,
            $result->outcome->isHeld() => InternalStatus::HeldSensitiveMatter,
            $result->outcome->isTerminal() => InternalStatus::ClosedDeclined,
            default => InternalStatus::AssessmentSubmitted,
        };
    }
}
