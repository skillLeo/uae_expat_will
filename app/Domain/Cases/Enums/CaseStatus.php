<?php

namespace App\Domain\Cases\Enums;

/**
 * The eight CUSTOMER-FACING status groups.
 *
 * This is the only status vocabulary a customer may ever see. The API exposes
 * the group, never the internal label — an endpoint returning
 * "Held — capacity or influence" is a data breach, not a bug.
 */
enum CaseStatus: string
{
    case AssessmentCompleted = 'assessment_completed';
    case UnderReview = 'under_review';
    case AcceptedPaymentRequired = 'accepted_payment_required';
    case QuestionnaireInProgress = 'questionnaire_in_progress';
    case LegalReviewAndDrafting = 'legal_review_drafting';
    case DraftReview = 'draft_review';
    case RegistrationInProgress = 'registration_in_progress';
    case Completed = 'completed';

    public function sequence(): int
    {
        return match ($this) {
            self::AssessmentCompleted => 1,
            self::UnderReview => 2,
            self::AcceptedPaymentRequired => 3,
            self::QuestionnaireInProgress => 4,
            self::LegalReviewAndDrafting => 5,
            self::DraftReview => 6,
            self::RegistrationInProgress => 7,
            self::Completed => 8,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::AssessmentCompleted => 'Assessment Completed',
            self::UnderReview => 'Under Review or Further Information Required',
            self::AcceptedPaymentRequired => 'Accepted, Terms and Payment Required',
            self::QuestionnaireInProgress => 'Questionnaire In Progress',
            self::LegalReviewAndDrafting => 'Legal Review and Drafting',
            self::DraftReview => 'Draft Review, Amendments or Approval',
            self::RegistrationInProgress => 'Registration Preparation or In Progress',
            self::Completed => 'Completed',
        };
    }

    /** Semantic tone. Under review is HELD, never critical. */
    public function tone(): string
    {
        return match ($this) {
            self::AssessmentCompleted => 'neutral',
            self::UnderReview => 'held',
            self::AcceptedPaymentRequired => 'attention',
            self::QuestionnaireInProgress,
            self::LegalReviewAndDrafting,
            self::DraftReview,
            self::RegistrationInProgress => 'progress',
            self::Completed => 'positive',
        };
    }
}
