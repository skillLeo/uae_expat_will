<?php

namespace App\Http\Controllers\Client\Auth;

use App\Domain\Audit\Services\AuditLogger;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class LoginController extends Controller
{
    public function __construct(private AuditLogger $audit) {}

    public function create(): Response
    {
        // Magic link is presented as an EQUAL option, not a fallback link in
        // small print — most people would rather not have another password.
        return Inertia::render('Client/Auth/Login');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email' => 'required|email|max:190',
            'password' => 'required|string',
        ]);

        $key = 'client-login|'.mb_strtolower($validated['email']).'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, (int) setting('security.max_login_attempts', 5))) {
            throw ValidationException::withMessages([
                'password' => 'Too many attempts. Try again in '.RateLimiter::availableIn($key).' seconds.',
            ]);
        }

        $user = User::customers()->where('email', $validated['email'])->first();

        if ($user === null || ! Hash::check($validated['password'], $user->password)) {
            RateLimiter::hit($key);
            $this->audit->loginFailed($validated['email']);

            // Never reveal whether the account exists.
            throw ValidationException::withMessages([
                'password' => 'Those details do not match our records.',
            ]);
        }

        if ($user->isDisabled()) {
            throw ValidationException::withMessages([
                'password' => 'This account is closed. Please contact our team.',
            ]);
        }

        RateLimiter::clear($key);
        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        $user->forceFill(['last_login_at' => now(), 'last_login_ip' => $request->ip()])->save();
        $this->audit->loginSucceeded($user);

        return redirect()->intended(route('client.dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }

    // ------------------------------------------------------- password reset

    public function requestReset(): Response
    {
        return Inertia::render('Client/Auth/ForgotPassword');
    }

    public function sendReset(Request $request): RedirectResponse
    {
        $validated = $request->validate(['email' => 'required|email']);

        Password::broker()->sendResetLink($validated);

        // Always the same message, whether or not the address is known.
        return back()->with('success', 'If that address has an account, a reset link is on its way.');
    }

    public function resetForm(Request $request, string $token): Response
    {
        return Inertia::render('Client/Auth/ResetPassword', [
            'token' => $token,
            'email' => $request->query('email'),
        ]);
    }

    public function reset(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'token' => 'required|string',
            'email' => 'required|email',
            'password' => ['required', 'confirmed', \Illuminate\Validation\Rules\Password::min(12)->uncompromised()],
        ]);

        $status = Password::broker()->reset($validated, function (User $user, string $password) {
            $user->forceFill(['password' => Hash::make($password)])->save();
            $this->audit->log('auth', 'Password reset', $user);
        });

        return $status === Password::PasswordReset
            ? redirect()->route('client.login')->with('success', 'Your password has been changed. Please sign in.')
            : back()->withErrors(['email' => 'That reset link is no longer valid. Please request a new one.']);
    }
}
