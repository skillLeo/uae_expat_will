<?php

namespace App\Http\Middleware;

use App\Models\SessionDevice;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Records the active sessions a user has, so they can be listed and revoked.
 *
 * Also enforces revocation: if this session's device row has been revoked, the
 * user is logged out on their very next request rather than at their next login.
 */
class TrackDeviceSession
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user() ?? $request->user('admin');

        if ($user === null || ! $request->hasSession()) {
            return $next($request);
        }

        $sessionId = $request->session()->getId();

        $device = SessionDevice::firstOrNew([
            'user_id' => $user->id,
            'session_id' => $sessionId,
        ]);

        if ($device->exists && $device->revoked_at !== null) {
            auth('web')->logout();
            auth('admin')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('admin.login')
                ->with('error', 'This session was signed out from another device.');
        }

        $device->fill([
            'ip' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 500),
            'device_label' => $device->device_label ?? $this->label($request->userAgent()),
            'last_active_at' => now(),
        ])->save();

        return $next($request);
    }

    private function label(?string $agent): string
    {
        $agent = (string) $agent;

        $platform = match (true) {
            str_contains($agent, 'iPhone') => 'iPhone',
            str_contains($agent, 'iPad') => 'iPad',
            str_contains($agent, 'Android') => 'Android',
            str_contains($agent, 'Macintosh') => 'Mac',
            str_contains($agent, 'Windows') => 'Windows',
            str_contains($agent, 'Linux') => 'Linux',
            default => 'Unknown device',
        };

        $browser = match (true) {
            str_contains($agent, 'Edg/') => 'Edge',
            str_contains($agent, 'Chrome') => 'Chrome',
            str_contains($agent, 'Safari') => 'Safari',
            str_contains($agent, 'Firefox') => 'Firefox',
            default => 'browser',
        };

        return $platform.' · '.$browser;
    }
}
