<?php

namespace App\Domain\Payments\Enums;

/**
 * What a payment is actually for.
 *
 * Until this existed the only thing separating Summit's own fee from a charge
 * levied by a court or registry was `stage_label`, a free-text box pre-filled
 * with "Professional fee". That made the two indistinguishable to every query,
 * report and rule in the system — and the refund engine, which has one set of
 * bands written entirely about Summit's fee, applied them to both.
 */
enum PaymentType: string
{
    /** Summit's own fee for the work. The refund bands are written about this. */
    case ProfessionalFee = 'professional_fee';

    /** A charge levied by a court, registry, notary or translator and passed
     *  straight through. Summit collects it; the money is not Summit's. */
    case Disbursement = 'disbursement';

    public function label(): string
    {
        return match ($this) {
            self::ProfessionalFee => 'Professional fee',
            self::Disbursement => 'Authority or third-party charge',
        };
    }

    /** For table columns and pills, where the long label will not fit. */
    public function shortLabel(): string
    {
        return match ($this) {
            self::ProfessionalFee => 'Professional',
            self::Disbursement => 'Authority',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::ProfessionalFee => 'Summit\'s fee for preparing and reviewing the Will. Refundable under the four bands in the Payment and Refund Policy.',
            self::Disbursement => 'A charge set by a court, registry, notary or translator, collected on their behalf. Once committed it is usually not recoverable, so it is never refunded by band.',
        };
    }

    public function isDisbursement(): bool
    {
        return $this === self::Disbursement;
    }

    /**
     * The VAT rate applied to this kind of payment.
     *
     * Both currently take the same rate from commercial settings, which means a
     * 750 authority charge is billed at 787.50. Whether VAT applies to a
     * pass-through government charge is a tax question for Summit's accountant,
     * not a decision for this codebase, so the behaviour is deliberately
     * unchanged.
     *
     * When the answer comes back, THIS METHOD is the only thing that changes —
     * `self::Disbursement => 0.0` and every call site is already correct.
     */
    public function vatRate(): float
    {
        return match ($this) {
            self::ProfessionalFee,
            self::Disbursement => (float) setting('commercial.vat_rate', 5),
        };
    }

    /** @return array<int, array{value: string, label: string, description: string}> */
    public static function options(): array
    {
        return array_map(fn (self $t) => [
            'value' => $t->value,
            'label' => $t->label(),
            'description' => $t->description(),
        ], self::cases());
    }
}
