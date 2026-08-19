<?php

namespace App\Http\Controllers\Admin\Auth;

use App\Domain\Audit\Services\AuditLogger;
use App\Http\Controllers\Controller;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use PragmaRX\Google2FA\Google2FA;

/**
 * Mandatory two-factor enrolment and challenge for Summit staff.
 *
 * Admin 2FA is contractual, so there is no "remind me later" path: an
 * unenrolled administrator can reach these screens and logout, and nothing else.
 */
class TwoFactorController extends Controller
{
    public function __construct(
        private Google2FA $google2fa,
        private AuditLogger $audit,
    ) {}

    public function enrol(Request $request): Response|RedirectResponse
    {
        $user = $request->user('admin');

        if ($user->hasTwoFactorEnabled()) {
            return redirect()->route('admin.dashboard');
        }

        // Keep the pending secret in the session until it is confirmed, so an
        // abandoned enrolment never half-enables 2FA on the account.
        $secret = $request->session()->get('2fa.pending_secret')
            ?? tap($this->google2fa->generateSecretKey(), fn ($s) => $request->session()->put('2fa.pending_secret', $s));

        $url = $this->google2fa->getQRCodeUrl(
            setting('branding.platform_name', 'UAE Expat Wills'),
            $user->email,
            $secret,
        );

        return Inertia::render('Admin/Auth/TwoFactorEnrol', [
            'secret' => $secret,
            'qr' => $this->qrSvg($url),
        ]);
    }

    public function confirm(Request $request): RedirectResponse
    {
        $validated = $request->validate(['code' => 'required|string|size:6']);
        $user = $request->user('admin');
        $secret = $request->session()->get('2fa.pending_secret');

        if (! $secret || ! $this->google2fa->verifyKey($secret, $validated['code'])) {
            throw ValidationException::withMessages(['code' => 'That code is not correct. Check your authenticator app and try again.']);
        }

        $codes = collect(range(1, 8))->map(fn () => Str::upper(Str::random(5).'-'.Str::random(5)))->all();

        $user->forceFill([
            'two_factor_secret' => $secret,
            'two_factor_recovery_codes' => $codes,
            'two_factor_confirmed_at' => now(),
        ])->save();

        $request->session()->forget('2fa.pending_secret');
        $request->session()->put('2fa.recovery_codes', $codes);
        $request->session()->put('2fa.passed', true);

        $this->audit->log('auth', 'Two-factor authentication enabled', $user);

        return redirect()->route('admin.two-factor.recovery-codes');
    }

    public function recoveryCodes(Request $request): Response
    {
        return Inertia::render('Admin/Auth/RecoveryCodes', [
            // Shown ONCE, straight after enrolment, and never again.
            'codes' => $request->session()->pull('2fa.recovery_codes', []),
        ]);
    }

    public function challenge(): Response
    {
        return Inertia::render('Admin/Auth/TwoFactorChallenge');
    }

    public function verify(Request $request): RedirectResponse
    {
        $validated = $request->validate(['code' => 'required|string|min:6|max:11']);
        $user = $request->user('admin');
        $code = str_replace(' ', '', $validated['code']);

        if ($this->google2fa->verifyKey($user->two_factor_secret, $code)) {
            $request->session()->put('2fa.passed', true);

            return redirect()->intended(route('admin.dashboard'));
        }

        // A recovery code is single use and is burned on success.
        $codes = $user->two_factor_recovery_codes ?? [];

        if (in_array($code, $codes, true)) {
            $user->forceFill([
                'two_factor_recovery_codes' => array_values(array_diff($codes, [$code])),
            ])->save();

            $request->session()->put('2fa.passed', true);
            $this->audit->log('auth', 'Signed in with a recovery code', $user);

            return redirect()->intended(route('admin.dashboard'));
        }

        $this->audit->log('auth', 'Failed two-factor attempt', $user);

        throw ValidationException::withMessages(['code' => 'That code is not correct.']);
    }

    private function qrSvg(string $url): string
    {
        $writer = new Writer(new ImageRenderer(new RendererStyle(220, 1), new SvgImageBackEnd));

        return 'data:image/svg+xml;base64,'.base64_encode($writer->writeString($url));
    }
}
