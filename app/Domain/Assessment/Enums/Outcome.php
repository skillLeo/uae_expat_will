<?php

namespace App\Domain\Assessment\Enums;

/**
 * The outcome vocabulary from Part 5.2 of the master specification.
 *
 * These are ordered by SEVERITY, and that order is the whole point: the engine
 * collects every rule that matches and then takes the most severe. See
 * Outcome::mostSevere().
 */
enum Outcome: string
{
    // Terminal. Order matters — see precedence() below.
    case StopIneligible = 'stop_ineligible';   // Q2 under 18, Q3 UAE citizen
    case StopRefer = 'stop_refer';             // Q1 someone has died
    case UrgentReview = 'urgent_review';       // Q15B capacity or undue influence

    // Non-terminal.
    case Review = 'review';                    // any single review sends the whole case
    case ContinueRouteMark = 'continue_route_mark';
    case ContinueReminder = 'continue_reminder';
    case ContinueFlag = 'continue_flag';
    case Continue_ = 'continue';

    /**
     * Precedence, first match wins. Lower number = resolved first.
     * Part 6.2 of the specification.
     */
    public function precedence(): int
    {
        return match ($this) {
            self::StopIneligible => 1,
            self::StopRefer => 2,
            self::UrgentReview => 3,
            self::Review => 4,
            // All the continue variants resolve to the same result screen; they
            // differ only in what they accumulate onto the case.
            self::ContinueRouteMark, self::ContinueReminder,
            self::ContinueFlag, self::Continue_ => 5,
        };
    }

    /** A terminal outcome ends the assessment immediately, skipping remaining questions. */
    public function isTerminal(): bool
    {
        return in_array($this, [self::StopIneligible, self::StopRefer, self::UrgentReview], true);
    }

    /** Whether this outcome permits a payment to be requested. Never guess this. */
    public function allowsPayment(): bool
    {
        return $this->precedence() === 5;
    }

    /** Whether the case is held for human review before anything is charged. */
    public function isHeld(): bool
    {
        return in_array($this, [self::Review, self::UrgentReview], true);
    }

    /**
     * The restricted outcomes. A restricted case's REASON must never appear in a
     * notification, an export, a list or a search result.
     */
    public function isRestricted(): bool
    {
        return $this === self::UrgentReview;
    }

    /** Which of the six result screens this outcome renders. */
    public function resultScreen(): string
    {
        return match ($this) {
            self::StopIneligible => 'ineligible',
            self::StopRefer => 'refer',
            self::UrgentReview => 'urgent_review',
            self::Review => 'review',
            default => 'continue',
        };
    }

    /** The semantic colour token. Held is never critical — a hold is not a rejection. */
    public function tone(): string
    {
        return match ($this) {
            self::StopIneligible, self::StopRefer => 'critical',
            self::UrgentReview, self::Review => 'held',
            default => 'positive',
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::StopIneligible => 'Cannot continue',
            self::StopRefer => 'Referred as a different service',
            self::UrgentReview => 'Urgent review',
            self::Review => 'Held for review',
            self::ContinueRouteMark => 'Continue — wider drafting route',
            self::ContinueReminder => 'Continue — information owed',
            self::ContinueFlag => 'Continue — flagged for reviewer',
            self::Continue_ => 'Continue online',
        };
    }

    /**
     * Resolve a set of matched outcomes down to the one that governs.
     *
     * @param  array<int, self>  $outcomes
     */
    /**
     * The outcome whose result screen this one should be shown on.
     *
     * Every continue variant resolves to the same screen — they differ only in
     * what they accumulate onto the case, never in what the customer reads.
     * Without this, the nineteen rules that now emit ContinueFlag would look up
     * a screen that was never seeded and the customer would be shown nothing.
     */
    public function screenOutcome(): self
    {
        return $this->precedence() === 5 ? self::Continue_ : $this;
    }

    public static function mostSevere(array $outcomes): self
    {
        if ($outcomes === []) {
            return self::Continue_;
        }

        usort($outcomes, fn (self $a, self $b) => $a->precedence() <=> $b->precedence());

        return $outcomes[0];
    }
}
