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
 * This runs as a GLOBAL web middleware matched on the path, rather than as
 * route middleware. Laravel sorts `Authenticate` early through its middleware
 * priority list, so a route-level gate ran AFTER it — and an unauthenticated
 * hit on /client redirected to sign-in, which is exactly the confirmation the
 * 404 exists to withhold. Running globally, nothing can be sorted in front.
 *
 * feature() returns FALSE for an absent or unreadable flag, so a missing
 * setting can never accidentally open this.
 */
class EnsureClientPortalEnabled
{
    /** Paths belonging to the gated client area. */
    private const GATED = ['client', 'client/*', 'access/*'];

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->is(...self::GATED) && ! feature('client_portal_enabled')) {
            abort(404);
        }

        return $next($request);
    }
}
