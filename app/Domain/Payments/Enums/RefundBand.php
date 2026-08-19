<?php

namespace App\Domain\Payments\Enums;

use App\Domain\Cases\Enums\CaseStage;

/**
 * The four refund bands.
 *
 * A band is derived ONLY from case_stage_timestamps — never from status, never
 * from a human's opinion of how much work was done. RefundCalculator picks the
 * band by asking which stages had occurred at the moment of the request.
 */
enum RefundBand: string
{
    /** Nothing substantive started. Full refund. */
    case A = 'a';

    /** Substantive work started, no draft delivered. Documented deduction. */
    case B = 'b';

    /** Draft delivered but not approved. The unused portion is refunded, computed
     *  from fee_allocations. */
    case C = 'c';

    /** Final approval, third-party cost committed, or submitted to the authority.
     *  No refund of the professional fee. */
    case D = 'd';

    public function label(): string
    {
        return match ($this) {
            self::A => 'Band A — full refund',
            self::B => 'Band B — refund less documented deduction',
            self::C => 'Band C — unused portion refunded',
            self::D => 'Band D — no refund of the professional fee',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::A => 'No substantive work had started when the refund was requested.',
            self::B => 'Substantive work had started but no draft had been delivered. A reasonable, documented amount for work completed is deducted.',
            self::C => 'A first draft had been delivered but the wording had not been approved. The unused portion of the fee is refunded, computed from the fee allocation table.',
            self::D => 'Final approval was recorded, a third-party cost had been committed, or the matter had been submitted to the authority.',
        };
    }

    /**
     * The stage that, once reached, puts a case into this band.
     * Evaluated highest-band-first by RefundCalculator.
     *
     * @return array<int, CaseStage>
     */
    public function triggeringStages(): array
    {
        return match ($this) {
            self::D => [CaseStage::FinalApproval, CaseStage::ThirdPartyCommitted, CaseStage::AuthoritySubmitted],
            self::C => [CaseStage::FirstDraftDelivered],
            self::B => [CaseStage::SubstantiveWorkStarted],
            self::A => [],
        };
    }
}
