<?php

namespace App\Domain\Cases\Enums;

/**
 * The internal statuses. NEVER rendered to a customer.
 *
 * Each maps up to exactly one customer-facing CaseStatus group. The mapping is
 * the security boundary: internal detail stays internal, and the group is what
 * leaves the building.
 */
enum InternalStatus: string
{
    // 01 — Assessment Completed
    case AssessmentSubmitted = 'assessment_submitted';
    case AwaitingTriage = 'awaiting_triage';

    // 01b — A lead that has given contact details but not finished the second
    // step is still a lead, and must be visible and contactable rather than
    // waiting for a submission that may never come.
    case ContactCapturedIncomplete = 'contact_captured_incomplete';

    // 02 — Under Review or Further Information Required
    case NewLegalReviewRequired = 'new_legal_review_required';
    case HeldDifcRoute = 'held_difc_route';
    case HeldSensitiveMatter = 'held_sensitive_matter';
    case HeldCapacityOrInfluence = 'held_capacity_or_influence'; // RESTRICTED
    case FurtherInformationRequested = 'further_information_requested';
    case AwaitingClientReply = 'awaiting_client_reply';

    // 03 — Accepted, Terms and Payment Required
    case AcceptedTermsIssued = 'accepted_terms_issued';
    case PaymentLinkSent = 'payment_link_sent';
    case PaymentFailedRetry = 'payment_failed_retry';

    // 04 — Questionnaire In Progress
    case QuestionnaireReleased = 'questionnaire_released';
    case QuestionnairePartiallyComplete = 'questionnaire_partially_complete';

    // 05 — Legal Review and Drafting
    case InLegalReview = 'in_legal_review';
    case Drafting = 'drafting';
    case InternalQa = 'internal_qa';

    // 06 — Draft Review, Amendments or Approval
    case DraftSent = 'draft_sent';
    case AmendmentRound1 = 'amendment_round_1';
    case AmendmentRound2 = 'amendment_round_2';
    case ApprovedByClient = 'approved_by_client';

    // 07 — Registration Preparation or In Progress
    case TranslationInProgress = 'translation_in_progress';
    case SubmissionPrepared = 'submission_prepared';
    case LodgedWithAuthority = 'lodged_with_authority';

    // 08 — Completed
    case RegisteredAndDelivered = 'registered_and_delivered';
    case ClosedCancelled = 'closed_cancelled';
    case ClosedDeclined = 'closed_declined';

    /** The customer-facing group this rolls up to. */
    public function group(): CaseStatus
    {
        return match ($this) {
            self::AssessmentSubmitted, self::AwaitingTriage,
            self::ContactCapturedIncomplete => CaseStatus::AssessmentCompleted,

            self::NewLegalReviewRequired => CaseStatus::UnderReview,

            self::HeldDifcRoute, self::HeldSensitiveMatter, self::HeldCapacityOrInfluence,
            self::FurtherInformationRequested, self::AwaitingClientReply => CaseStatus::UnderReview,

            self::AcceptedTermsIssued, self::PaymentLinkSent,
            self::PaymentFailedRetry => CaseStatus::AcceptedPaymentRequired,

            self::QuestionnaireReleased,
            self::QuestionnairePartiallyComplete => CaseStatus::QuestionnaireInProgress,

            self::InLegalReview, self::Drafting,
            self::InternalQa => CaseStatus::LegalReviewAndDrafting,

            self::DraftSent, self::AmendmentRound1, self::AmendmentRound2,
            self::ApprovedByClient => CaseStatus::DraftReview,

            self::TranslationInProgress, self::SubmissionPrepared,
            self::LodgedWithAuthority => CaseStatus::RegistrationInProgress,

            self::RegisteredAndDelivered, self::ClosedCancelled,
            self::ClosedDeclined => CaseStatus::Completed,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::AssessmentSubmitted => 'Assessment submitted',
            self::AwaitingTriage => 'Awaiting triage',
            self::ContactCapturedIncomplete => 'Contact captured — details incomplete',
            self::NewLegalReviewRequired => 'New — legal review required',
            self::HeldDifcRoute => 'Held — DIFC route',
            self::HeldSensitiveMatter => 'Held — sensitive matter',
            self::HeldCapacityOrInfluence => 'Held — capacity or influence',
            self::FurtherInformationRequested => 'Further information requested',
            self::AwaitingClientReply => 'Awaiting client reply',
            self::AcceptedTermsIssued => 'Accepted — terms issued',
            self::PaymentLinkSent => 'Payment link sent',
            self::PaymentFailedRetry => 'Payment failed — retry',
            self::QuestionnaireReleased => 'Questionnaire released',
            self::QuestionnairePartiallyComplete => 'Questionnaire partially complete',
            self::InLegalReview => 'In legal review',
            self::Drafting => 'Drafting',
            self::InternalQa => 'Internal QA',
            self::DraftSent => 'Draft sent',
            self::AmendmentRound1 => 'Amendment round 1',
            self::AmendmentRound2 => 'Amendment round 2',
            self::ApprovedByClient => 'Approved by client',
            self::TranslationInProgress => 'Translation in progress',
            self::SubmissionPrepared => 'Submission prepared',
            self::LodgedWithAuthority => 'Lodged with authority',
            self::RegisteredAndDelivered => 'Registered and delivered',
            self::ClosedCancelled => 'Closed — cancelled',
            self::ClosedDeclined => 'Closed — declined',
        };
    }

    /**
     * Whether reaching this status restricts the case.
     *
     * Only capacity or influence does. Restricting a case hides its body from
     * every user without cases.view_restricted.
     */
    public function restrictsCase(): bool
    {
        return $this === self::HeldCapacityOrInfluence;
    }

    /**
     * Whether a payment may be requested while in this status.
     *
     * Held matters are the obvious no. The less obvious one is a specialist
     * request whose contact step is done but whose details are not: it groups
     * under "assessment completed" so the team can see and chase it, but it is
     * an existing-Will or estate enquiry and neither is priced before a human
     * has looked at it.
     */
    public function allowsPayment(): bool
    {
        if ($this === self::ContactCapturedIncomplete) {
            return false;
        }

        return $this->group() !== CaseStatus::UnderReview;
    }

    /** @return array<int, self> */
    public static function forGroup(CaseStatus $group): array
    {
        return array_values(array_filter(self::cases(), fn (self $s) => $s->group() === $group));
    }
}
