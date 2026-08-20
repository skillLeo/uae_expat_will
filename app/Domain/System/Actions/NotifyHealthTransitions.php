<?php

namespace App\Domain\System\Actions;

use App\Domain\Notifications\Services\RuntimeMailer;
use App\Domain\System\DTOs\HealthCheck;
use App\Domain\System\Enums\HealthState;
use App\Models\SystemHealthState;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Emails Summit the first time a check crosses into critical.
 *
 * Once per transition, never repeatedly: an alert that arrives every minute is
 * an alert people filter into a folder, and then the next real one is missed
 * too. Recovery is recorded silently — knowing it came back matters less than
 * not being nagged.
 *
 * THE ALERT CARRIES NO CASE DATA. Not a reference, not a name, not a count of
 * anything client-related. It names the check, what it means, and what to do.
 *
 * @return array<int, string> the keys that newly alerted
 */
class NotifyHealthTransitions
{
    public function __construct(private RuntimeMailer $mailer) {}

    /** @param array<int, HealthCheck> $checks */
    public function execute(array $checks): array
    {
        $alerted = [];

        foreach ($checks as $check) {
            $previous = SystemHealthState::firstOrNew(['check_key' => $check->key]);
            $wasCritical = $previous->exists && $previous->state === HealthState::Critical->value;
            $isCritical = $check->state->isAlertable();

            if ($previous->state !== $check->state->value) {
                $previous->fill(['state' => $check->state->value, 'changed_at' => now()])->save();
            }

            // Only the crossing fires. Staying critical does not.
            if (! $isCritical || $wasCritical) {
                continue;
            }

            if ($this->send($check)) {
                $previous->forceFill(['notified_at' => now()])->save();
                $alerted[] = $check->key;
            }
        }

        return $alerted;
    }

    private function send(HealthCheck $check): bool
    {
        $to = setting('contact.email');

        if (blank($to)) {
            Log::warning('Health alert not sent: no contact address configured', ['check' => $check->key]);

            return false;
        }

        $body = implode("\n\n", array_filter([
            'A system check on the UAE Expat Wills platform has started failing.',
            'Check: '.$check->label,
            'Status: '.$check->summary,
            $check->consequence ? 'What this means: '.$check->consequence : null,
            $check->fix ? 'What to do: '.$check->fix : null,
            $check->fixIsHostPanel
                ? 'This is fixed in the hosting control panel rather than in the admin area. The exact steps are in DEPLOYMENT.md.'
                : 'This is fixed from the admin area.',
            'You will not receive another message about this check until it recovers and fails again.',
        ]));

        try {
            $this->mailer->apply();

            Mail::raw($body, fn ($m) => $m->to($to)
                ->subject('[UAE Expat Wills] '.$check->label.' is not working'));

            return true;
        } catch (Throwable $e) {
            // If mail itself is what broke, the dashboard is the fallback — that
            // is exactly why the panel exists and does not rely on email.
            Log::error('Health alert could not be emailed', [
                'check' => $check->key,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
