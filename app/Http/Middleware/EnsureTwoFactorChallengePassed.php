<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * An enrolled administrator must pass the 2FA challenge each session.
 *
 * Having a secret on the account is not the same as having proved possession of
 * the device in THIS session.
 */
class EnsureTwoFactorChallengePassed
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user('admin');

        if ($user === null || ! $user->hasTwoFactorEnabled()) {
            return $next($request);
        }

        if ($request->session()->get('2fa.passed') === true) {
            return $next($request);
        }

        if ($request->routeIs('admin.two-factor.*', 'admin.logout')) {
            return $next($request);
        }

        return redirect()->route('admin.two-factor.challenge');
    }
}
