<?php

namespace App\Http\Middleware;

use App\Domain\Settings\Services\SettingsRepository;
use Illuminate\Http\Request;
use Inertia\Middleware;
use Tighten\Ziggy\Ziggy;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $settings = app(SettingsRepository::class);
        $user = $request->user() ?? $request->user('admin');

        return array_merge(parent::share($request), [
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'user_type' => $user->user_type,
                    'two_factor_enabled' => $user->hasTwoFactorEnabled(),
                    'roles' => $user->getRoleNames(),
                ] : null,

                // Only the permissions this user ACTUALLY holds are shipped. The
                // frontend uses them to hide controls, but that is cosmetic —
                // every controller re-checks. Never send the full permission list.
                'permissions' => $user
                    ? $user->getAllPermissions()->pluck('name')->values()
                    : [],
            ],

            // Public settings only. Credentials are excluded by the is_public
            // column, not by remembering to filter them here.
            'settings' => fn () => $settings->public(),

            'features' => fn () => [
                'client_portal_enabled' => $settings->feature('client_portal_enabled'),
                'client_login_in_header' => $settings->feature('client_login_in_header'),
                'document_upload_enabled' => $settings->feature('document_upload_enabled'),
                'whatsapp_enabled' => $settings->feature('whatsapp_enabled'),
                'self_serve_checkout_enabled' => $settings->feature('self_serve_checkout_enabled'),
            ],

            // Guarded: an error response can be rendered for a request that
            // never reached the session middleware (a 404 matches no route, so
            // the web group never runs). Reading the session there throws.
            'flash' => fn () => $request->hasSession() ? [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
                'warning' => $request->session()->get('warning'),
            ] : ['success' => null, 'error' => null, 'warning' => null],

            'ziggy' => fn () => [
                ...(new Ziggy)->toArray(),
                'location' => $request->url(),
            ],

            'locale' => fn () => app()->getLocale(),
            'supportedLocales' => fn () => config('app.supported_locales', ['en']),

            // The interface strings. Page content is NOT here — it comes from
            // the database with its own locale column, so Summit edits it and a
            // second language is a second row rather than a second deployment.
            'translations' => fn () => trans('ui'),
        ]);
    }
}
