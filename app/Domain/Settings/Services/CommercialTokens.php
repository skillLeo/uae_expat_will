<?php

namespace App\Domain\Settings\Services;

/**
 * The commercial placeholders, defined once.
 *
 * The fee appears in page copy, in FAQ answers, in two legal clauses and in
 * three notification templates. When it was written out as a literal in each of
 * those, changing the price meant six edits and it was found that the pages and
 * the Terms disagreed — the site advertised one number while the contract said
 * another. That is a dispute waiting to happen, not a typo.
 *
 * So every one of those places holds a token, and this class is the only thing
 * that knows what a token resolves to. Change commercial.standard_fee in
 * Settings and the whole site, the emails and the legal clauses move together.
 */
class CommercialTokens
{
    /** @return array<string, string> */
    public function all(): array
    {
        $vatRate = (float) setting('commercial.vat_rate', 5);
        $fee = (float) setting('commercial.standard_fee', 1999);
        $mirror = (float) setting('commercial.mirror_fee', 2999);

        return [
            // Headline figures, as they read in a sentence.
            '{fee}' => number_format($fee),
            '{mirror_fee}' => number_format($mirror),
            '{difc_fee}' => number_format((float) setting('commercial.difc_starting_fee', 3999)),

            // Two-decimal forms, for receipt tables and price displays where a
            // bare "1,999" next to "99.95" would look wrong.
            '{fee_2dp}' => number_format($fee, 2),
            '{vat_2dp}' => number_format($this->vat($fee, $vatRate), 2),
            '{total_2dp}' => number_format($fee + $this->vat($fee, $vatRate), 2),

            '{mirror_fee_2dp}' => number_format($mirror, 2),
            '{mirror_vat_2dp}' => number_format($this->vat($mirror, $vatRate), 2),
            '{mirror_total_2dp}' => number_format($mirror + $this->vat($mirror, $vatRate), 2),

            '{vat_rate}' => (string) setting('commercial.vat_rate', 5),
            '{currency}' => (string) setting('commercial.currency', 'AED'),
            '{first_draft_days}' => (string) setting('commercial.first_draft_days', 2),
            '{amendment_rounds}' => (string) setting('commercial.amendment_allowance', 2),
            '{trade_licence}' => (string) setting('branding.trade_licence'),
        ];
    }

    /** Rounded the way an invoice rounds it, so the parts always sum to the total. */
    private function vat(float $amount, float $rate): float
    {
        return round($amount * $rate / 100, 2);
    }

    public function apply(string $text): string
    {
        return strtr($text, $this->all());
    }
}
