<?php

namespace App\Http\Controllers\Admin\Auth;

use App\Domain\Audit\Services\AuditLogger;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Two-step admin sign-in: email, then password.
 *
 * Neither step ever reveals whether an email exists. The identify step always
 * advances, and the password step always returns the same message — so the form
 * cannot be used to enumerate Summit's staff.
 */
class LoginController extends Controller
{
    public function __construct(private AuditLogger $audit) {}

    public function create(Request $request): Response
    {
        return Inertia::render('Admin/Auth/Login', [
            'step' => $request->session()->get('login.email') ? 'password' : 'email',
            'email' => $request->session()->get('login.email'),
        ]);
    }

    /** Step one. Always advances, whether or not the account exists. */
    public function identify(Request $request): RedirectResponse
    {
        $validated = $request->validate(['email' => 'required|email|max:190']);

        $request->session()->put('login.email', $validated['email']);

        return back();
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email' => 'required|email|max:190',
            'password' => 'required|string',
            'session_length' => 'nullable|string|in:short,standard,extended',
        ]);

        $this->assertNotRateLimited($request, $validated['email']);

        $user = User::admins()->where('email', $validated['email'])->first();

        if ($user === null || ! Hash::check($validated['password'], $user->password)) {
            RateLimiter::hit($this->throttleKey($request, $validated['email']));
            $this->audit->loginFailed($validated['email']);

            if ($user !== null) {
                $user->increment('failed_login_count');
                $this->lockIfNeeded($user);
            }

            // Identical message either way.
            throw ValidationException::withMessages([
                'password' => 'Those details do not match our records.',
            ]);
        }

        if ($user->isLockedOut()) {
            throw ValidationException::withMessages([
                'password' => 'This account is locked. Try again in '
                    .ceil($user->secondsUntilUnlock() / 60).' minutes.',
            ]);
        }

        if ($user->isDisabled()) {
            $request->session()->put('disabled_user_id', $user->id);

            return redirect()->route('admin.disabled');
        }

        RateLimiter::clear($this->throttleKey($request, $validated['email']));

        Auth::guard('admin')->login($user);
        $request->session()->regenerate();
        $request->session()->forget('login.email');

        // A session-length choice instead of "remember me" — the user is told
        // how long they are choosing to stay signed in.
        $request->session()->put('session_length', $validated['session_length'] ?? 'standard');

        $user->forceFill([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
            'failed_login_count' => 0,
            'locked_until' => null,
        ])->save();

        $this->audit->loginSucceeded($user);

        return redirect()->intended(route('admin.dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        $user = $request->user('admin');

        if ($user) {
            $this->audit->log('auth', 'Signed out', $user);
        }

        Auth::guard('admin')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }

    public function disabled(Request $request): Response
    {
        $user = User::find($request->session()->get('disabled_user_id'));

        return Inertia::render('Admin/Auth/Disabled', [
            // The screen states WHO disabled the account and why, so the person
            // knows who to talk to rather than filing a support ticket.
            'disabledBy' => $user?->disabledBy?->name,
            'disabledAt' => $user?->disabled_at?->toDayDateTimeString(),
            'reason' => $user?->disabled_reason,
        ]);
    }

    private function assertNotRateLimited(Request $request, string $email): void
    {
        $key = $this->throttleKey($request, $email);
        $max = (int) setting('security.max_login_attempts', 5);

        if (! RateLimiter::tooManyAttempts($key, $max)) {
            return;
        }

        // A real countdown, not "try again later".
        throw ValidationException::withMessages([
            'password' => 'Too many attempts. Try again in '
                .RateLimiter::availableIn($key).' seconds.',
        ]);
    }

    private function throttleKey(Request $request, string $email): string
    {
        return 'admin-login|'.mb_strtolower($email).'|'.$request->ip();
    }

    private function lockIfNeeded(User $user): void
    {
        $max = (int) setting('security.max_login_attempts', 5);

        if ($user->failed_login_count >= $max) {
            $user->forceFill([
                'locked_until' => now()->addMinutes((int) setting('security.lockout_minutes', 15)),
            ])->save();
        }
    }
}
