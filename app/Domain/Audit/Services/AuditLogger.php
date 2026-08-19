<?php

namespace App\Domain\Audit\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Writes an audit entry with the request context that makes it evidential.
 *
 * The contract requires logins (successful AND failed), case access,
 * assignments, status changes, questionnaire and routing changes, payment
 * actions, permission changes, notifications and exports. Everything routes
 * through here so the ip/user-agent/route columns are never forgotten.
 */
class AuditLogger
{
    /** @param array<string, mixed> $properties */
    public function log(string $event, string $description, ?Model $subject = null, array $properties = []): void
    {
        $activity = activity($event)
            ->causedBy(Auth::user() ?? Auth::guard('admin')->user())
            ->withProperties($properties);

        if ($subject !== null) {
            $activity->performedOn($subject);
        }

        // The ip/user-agent/route columns are filled on insert by AuditActivity.
        // They cannot be added afterwards — the table is append-only and the
        // database rejects the update, which is the whole point.
        $activity->log($description);
    }

    public function loginSucceeded(Model $user): void
    {
        $this->log('auth', 'Signed in', $user);
    }

    /** Failed logins are logged WITHOUT confirming whether the email exists. */
    public function loginFailed(string $email): void
    {
        $this->log('auth', 'Failed sign-in attempt', null, ['email_hash' => hash('sha256', $email)]);
    }

    public function caseAccessed(Model $case): void
    {
        $this->log('case_access', 'Case opened', $case);
    }

    public function exported(string $what, int $rows): void
    {
        $this->log('export', "Exported {$what}", null, ['rows' => $rows]);
    }
}
