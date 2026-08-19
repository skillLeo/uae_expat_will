<?php

namespace App\Providers;

use App\Domain\Notifications\Contracts\WhatsAppClient;
use App\Domain\Notifications\Services\MetaWhatsAppClient;
use App\Domain\Payments\Contracts\PaymentGateway;
use App\Domain\Payments\Drivers\TelrDriver;
use App\Domain\Settings\Services\SettingsRepository;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SettingsRepository::class);

        // The gateway is resolved from a SETTING, so adding a provider is a new
        // driver class plus a value — never a change to the calling code.
        $this->app->bind(PaymentGateway::class, function ($app) {
            $gateway = $app->make(SettingsRepository::class)->get('payment.gateway', 'telr');

            return match ($gateway) {
                'telr' => $app->make(TelrDriver::class),
                default => $app->make(TelrDriver::class),
            };
        });

        $this->app->bind(WhatsAppClient::class, MetaWhatsAppClient::class);
    }

    public function boot(): void
    {
        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }

        $this->configureRateLimiting();
    }

    /**
     * Throttles on every auth and webhook route.
     *
     * Login is limited per email AND per IP: per-email alone lets one attacker
     * spray many accounts from one address, and per-IP alone lets a botnet grind
     * a single account.
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('login', function (Request $request) {
            $email = (string) $request->input('email');

            return [
                Limit::perMinute(5)->by($email.'|'.$request->ip()),
                Limit::perMinute(20)->by($request->ip()),
            ];
        });

        RateLimiter::for('two-factor', fn (Request $request) => Limit::perMinute(5)->by(
            ($request->user('admin')?->id ?? $request->ip()).'|2fa'
        ));

        RateLimiter::for('magic-link', fn (Request $request) => Limit::perMinute(10)->by($request->ip()));

        RateLimiter::for('webhooks', fn (Request $request) => Limit::perMinute(120)->by($request->ip()));

        RateLimiter::for('assessment', fn (Request $request) => Limit::perMinute(60)->by($request->ip()));
    }
}
