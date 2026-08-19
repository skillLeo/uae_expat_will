<?php

namespace App\Http\Controllers\Client\Auth;

use App\Domain\Audit\Services\AuditLogger;
use App\Http\Controllers\Controller;
use App\Models\Consent;
use App\Models\LegalCase;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Customer registration.
 *
 * The case reference and the assessment outcome are carried forward from the
 * assessment, so somebody who has just been told they may continue does not
 * arrive at a blank form with no idea whether it is the right one.
 */
class RegisterController extends Controller
{
    public function __construct(private AuditLogger $audit) {}

    public function create(Request $request): Response
    {
        $reference = $request->session()->get('assessment_case_reference')
            ?? $request->query('reference');

        $case = $reference
            ? LegalCase::where('reference', $reference)->with('assessment')->first()
            : null;

        return Inertia::render('Client/Auth/Register', [
            'reference' => $case?->reference,
            // The OUTCOME travels; the reason never does.
            'outcome' => $case?->assessment?->outcome?->value,
            'outcomeLabel' => $case?->assessment?->outcome?->label(),
            'allowsPayment' => $case?->allowsPayment() ?? false,
            'prefill' => [
                'full_name' => $case?->customer?->full_name,
                'email' => $case?->customer?->email,
                'phone' => $case?->customer?->phone,
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'full_name' => 'required|string|max:160',
            'email' => 'required|email|max:190|unique:users,email',
            'phone' => 'nullable|string|max:32',
            // Entropy-based, not a checklist of character classes: length and
            // not being in a breach corpus are what actually matter.
            'password' => ['required', 'confirmed', Password::min(12)->uncompromised()],
            'reference' => 'nullable|string|max:20',
            'accept_terms' => 'accepted',
        ]);

        return DB::transaction(function () use ($validated, $request) {
            $user = User::create([
                'user_type' => User::TYPE_CUSTOMER,
                'name' => $validated['full_name'],
                'email' => $validated['email'],
                'phone' => $validated['phone'] ?? null,
                'password' => Hash::make($validated['password']),
                'is_active' => true,
                'timezone' => 'Asia/Dubai',
            ]);

            $user->assignRole('Customer');

            // Attach the case that the assessment opened, if there is one.
            if (! empty($validated['reference'])) {
                $case = LegalCase::where('reference', $validated['reference'])->first();

                if ($case?->customer) {
                    $case->customer->update(['user_id' => $user->id]);
                }
            }

            Consent::create([
                'user_id' => $user->id,
                'type' => 'terms',
                'version' => (string) now()->format('Y-m-d'),
                'wording_hash' => Consent::hashWording('terms-and-conditions|privacy-policy'),
                'accepted' => true,
                'ip_address' => $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 500),
                'language' => app()->getLocale(),
                'related_reference' => $validated['reference'] ?? null,
                'accepted_at' => now(),
            ]);

            event(new Registered($user));
            Auth::guard('web')->login($user);
            $request->session()->regenerate();

            $this->audit->log('auth', 'Customer registered', $user);

            return redirect()->route('client.dashboard');
        });
    }
}
