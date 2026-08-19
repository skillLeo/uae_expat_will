<?php

namespace App\Domain\Cases\Enums;

/**
 * The stages whose timestamps drive the refund engine.
 *
 * A refund band cannot be computed from a case's status, because status moves
 * backwards and forwards. It is computed from which of these stages had actually
 * occurred at the moment the refund was requested. That is why every one of
 * these is recorded once, with the time it happened and who recorded it.
 */
enum CaseStage: string
{
    case Payment = 'payment';
    case SubstantiveWorkStarted = 'substantive_work_started';
    case FirstDraftDelivered = 'first_draft_delivered';
    case FinalApproval = 'final_approval';
    case ThirdPartyCommitted = 'third_party_committed';
    case AuthoritySubmitted = 'authority_submitted';

    public function label(): string
    {
        return match ($this) {
            self::Payment => 'Payment received',
            self::SubstantiveWorkStarted => 'Substantive work started',
            self::FirstDraftDelivered => 'First draft delivered',
            self::FinalApproval => 'Final approval recorded',
            self::ThirdPartyCommitted => 'Third-party cost committed',
            self::AuthoritySubmitted => 'Submitted to the authority',
        };
    }

    public function order(): int
    {
        return match ($this) {
            self::Payment => 1,
            self::SubstantiveWorkStarted => 2,
            self::FirstDraftDelivered => 3,
            self::FinalApproval => 4,
            self::ThirdPartyCommitted => 5,
            self::AuthoritySubmitted => 6,
        };
    }
}
