<?php

namespace App\Domain\Cases\Actions;

use App\Models\LegalCase;
use App\Models\MagicLink;

/**
 * Issues a single-use, time-limited magic link for exactly one case.
 *
 * The raw token is returned from here ONCE and never stored. What goes in the
 * database is its SHA-256 hash, so a database leak yields no usable links, and
 * "resend my link" genuinely means issuing a new one rather than recovering the
 * old one.
 */
class IssueMagicLink
{
    /** @return array{link: MagicLink, url: string} */
    public function execute(LegalCase $case, string $purpose = 'detailed_questionnaire', int $hours = 24): array
    {
        // Issuing a new link retires any outstanding one for the same purpose.
        // Two live links for one case is one more than anybody needs.
        MagicLink::where('case_id', $case->id)
            ->where('purpose', $purpose)
            ->whereNull('used_at')
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);

        $raw = bin2hex(random_bytes(32));

        $link = MagicLink::create([
            'case_id' => $case->id,
            'purpose' => $purpose,
            'token_hash' => MagicLink::hash($raw),
            'expires_at' => now()->addHours($hours),
        ]);

        return [
            'link' => $link,
            // Delivered over HTTPS only. The raw token exists nowhere else.
            'url' => url("/access/{$raw}"),
        ];
    }
}
