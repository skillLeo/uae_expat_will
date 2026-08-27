<?php

namespace App\Domain\Cases\Enums;

/**
 * What the visitor actually asked for at question one.
 *
 * Two of these never reach a checkout. Amending an existing Will and
 * administering an estate after a death are different legal services
 * altogether, and Ahmed's instruction of 25 August was that both should skip
 * the questionnaire and go straight to the team rather than being shown a page
 * saying the online Will service is not available.
 *
 * DIFC is deliberately absent: it belongs to a different project.
 */
enum RequestType: string
{
    case StandardWill = 'standard_will';
    case MirrorWills = 'mirror_wills';
    case ExistingWillService = 'existing_will_service';
    case EstateAdministration = 'estate_administration';

    public function label(): string
    {
        return match ($this) {
            self::StandardWill => 'Standard Will',
            self::MirrorWills => 'Mirror Wills',
            self::ExistingWillService => 'Existing Will Service',
            self::EstateAdministration => 'Estate Administration',
        };
    }

    /** The two that skip the assessment entirely and go straight to a form. */
    public function isDirectSpecialistRequest(): bool
    {
        return in_array($this, [self::ExistingWillService, self::EstateAdministration], true);
    }

    /** The answer to question one that leads here. */
    public static function fromServiceAnswer(?string $answer): self
    {
        return match ($answer) {
            'two_wills' => self::MirrorWills,
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
