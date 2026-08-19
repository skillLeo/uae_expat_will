<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Admin 2FA is MANDATORY and contractual.
 *
 * An administrator who has not completed enrolment is allowed exactly two
 * places: the enrolment screens and logout. Everything else redirects back to
 * enrolment — there is no "remind me later".
 */
class EnsureTwoFactorIsConfirmed
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user('admin');

        if ($user === null) {
            return $next($request);
        }

        if ($user->hasTwoFactorEnabled() || ! $user->requiresTwoFactor()) {
            return $next($request);
        }

        if ($request->routeIs('admin.two-factor.*', 'admin.logout')) {
            return $next($request);
        }

        return redirect()->route('admin.two-factor.enrol')
            ->with('warning', 'Two-factor authentication must be set up before you can continue.');
    }
}
