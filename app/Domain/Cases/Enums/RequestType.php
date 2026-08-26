<?php

namespace App\Domain\Cases\Enums;

/**
 * What the visitor actually asked for at question one.
 *
 * Three of these never reach a checkout. DIFC needs a quotation, and an
 * existing Will or an estate after death are different legal services
 * altogether — Summit's instruction is explicit that the platform must never
 * tell those people the service is "not available".
 */
enum RequestType: string
{
    case StandardWill = 'standard_will';
    case MirrorWills = 'mirror_wills';
    case DifcWill = 'difc_will';
    case ExistingWillService = 'existing_will_service';
    case EstateAdministration = 'estate_administration';

    public function label(): string
    {
        return match ($this) {
            self::StandardWill => 'Standard Will',
            self::MirrorWills => 'Mirror Wills',
            self::DifcWill => 'DIFC Will',
            self::ExistingWillService => 'Existing Will Service',
            self::EstateAdministration => 'Estate Administration',
        };
    }

    /** The two that skip the assessment entirely and go straight to a form. */
    public function isDirectSpecialistRequest(): bool
    {
        return in_array($this, [self::ExistingWillService, self::EstateAdministration], true);
    }

    /** Nothing is ever charged for these before a human has looked. */
    public function requiresQuotationFirst(): bool
    {
        return in_array($this, [
            self::DifcWill,
            self::ExistingWillService,
            self::EstateAdministration,
        ], true);
    }

    /** The answer to question one that leads here. */
    public static function fromServiceAnswer(?string $answer): self
    {
        return match ($answer) {
            'two_wills' => self::MirrorWills,
            'difc' => self::DifcWill,
            'review_existing' => self::ExistingWillService,
            'estate_death' => self::EstateAdministration,
            default => self::StandardWill,
        };
    }

    /** The message shown on the request form, per Summit's handoff. */
    public function requestFormNote(): ?string
    {
        return match ($this) {
            self::ExistingWillService => 'Please tell us whether you want to review, amend, replace or revoke an '
                .'existing Will. Our legal team will assess the document and advise you on the appropriate next step.',
            self::EstateAdministration => 'This is an estate administration or succession matter rather than a '
                .'Will-preparation service. Our legal team will review the circumstances and contact you regarding '
                .'the available legal steps.',
            default => null,
        };
    }
}
