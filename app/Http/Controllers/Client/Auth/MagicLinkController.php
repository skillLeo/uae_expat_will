<?php

namespace App\Http\Controllers\Client\Auth;

use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Cases\Actions\IssueMagicLink;
use App\Domain\Notifications\Enums\NotificationChannel;
use App\Domain\Notifications\Services\NotificationDispatcher;
use App\Http\Controllers\Controller;
use App\Models\LegalCase;
use App\Models\MagicLink;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Magic-link sign-in, and the link that releases the detailed questionnaire.
 *
 * A link grants access to exactly one case and never escalates a session: it
 * signs the customer in as themselves, not as anybody with wider access.
 *
 * The four failure states are four DISTINCT screens, because "this link does
 * not work" tells somebody nothing about what to do next.
 */
class MagicLinkController extends Controller
{
    public function __construct(
        private IssueMagicLink $issue,
        private NotificationDispatcher $dispatcher,
        private AuditLogger $audit,
    ) {}

    public function create(): Response
    {
        return Inertia::render('Client/Auth/MagicLink');
    }

    public function send(Request $request): RedirectResponse
    {
        $validated = $request->validate(['email' => 'required|email|max:190']);

        $case = LegalCase::whereHas('customer', fn ($q) => $q->where('email', $validated['email']))
            ->latest()
            ->first();

        if ($case !== null) {
            ['url' => $url] = $this->issue->execute($case, 'sign_in', hours: 1);

            $this->dispatcher->send(
                'questionnaire_released',
                NotificationChannel::Email,
                $validated['email'],
                ['first_name' => $case->customer?->firstName() ?? 'there', 'reference' => $case->reference, 'link' => $url],
                $case,
            );
        }

        // The same screen either way — this must not become an address checker.
        return redirect()->route('client.magic-link.sent');
    }

    public function sent(): Response
    {
        return Inertia::render('Client/Auth/MagicLinkSent');
    }

    /** Consumes a link. Single use, time limited, revocable. */
    public function consume(Request $request, string $token): RedirectResponse|Response
    {
        $link = MagicLink::where('token_hash', MagicLink::hash($token))->first();

        if ($link === null) {
            return Inertia::render('Client/Auth/MagicLinkProblem', ['reason' => 'invalid']);
        }

        if (! $link->isUsable()) {
            return Inertia::render('Client/Auth/MagicLinkProblem', [
                'reason' => $link->failureReason(),
                'reference' => $link->legalCase?->reference,
            ]);
        }

        $case = $link->legalCase;
        $user = $case?->customer?->user;

        if ($user === null) {
            return Inertia::render('Client/Auth/MagicLinkProblem', ['reason' => 'no_account', 'reference' => $case?->reference]);
        }

        // Burned on use, with the context it was used from recorded.
        $link->update([
            'used_at' => now(),
            'ip_used' => $request->ip(),
            'user_agent_used' => substr((string) $request->userAgent(), 0, 500),
        ]);

        Auth::guard('web')->login($user);
        $request->session()->regenerate();
        $request->session()->put('magic_link_case_id', $case->id);

        $this->audit->log('auth', 'Signed in with a magic link', $user, ['case' => $case->reference]);

        return redirect()->route('client.dashboard');
    }
}
