<?php

namespace App\Models;

use Illuminate\Support\Facades\Request;
use Spatie\Activitylog\Models\Activity as SpatieActivity;

/**
 * The audit log entry.
 *
 * The request context (IP, user agent, route) is filled ON INSERT rather than
 * by a follow-up update, because the activity_log table is append-only and the
 * database trigger rejects any UPDATE — including a well-meaning one from this
 * application. Writing it here is the only way the columns can ever be
 * populated, which is exactly the property we want.
 *
 * This model also refuses to be updated or deleted from application code, so
 * the guarantee holds even against a driver with no trigger support.
 */
class AuditActivity extends SpatieActivity
{
    protected static function booted(): void
    {
        static::creating(function (self $activity) {
            if (! app()->runningInConsole() || app()->runningUnitTests()) {
                $activity->ip_address ??= Request::ip();
                $activity->user_agent ??= substr((string) Request::userAgent(), 0, 500);
                $activity->route ??= Request::route()?->getName() ?? Request::path();
            }
        });

        // Append only. No exceptions, no "just this once".
        static::updating(fn () => throw new \RuntimeException('activity_log is append-only'));
        static::deleting(fn () => throw new \RuntimeException('activity_log is append-only'));
    }
}
