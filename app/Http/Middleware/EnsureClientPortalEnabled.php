<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The commercial gate on the client area.
 *
 * The whole area is built, but it is not reachable until Summit approves that
 * phase in writing. A 404 rather than a 403 is deliberate: a 403 confirms the
 * area exists, and there is no reason to advertise an unapproved phase.
 *
 * feature() returns FALSE for an absent or unreadable flag, so a missing
 * setting can never accidentally open this.
 */
class EnsureClientPortalEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(feature('client_portal_enabled'), 404);

        return $next($request);
    }
}
