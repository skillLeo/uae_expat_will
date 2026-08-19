<?php

use App\Domain\Notifications\Services\RuntimeMailer;
use App\Domain\Payments\Contracts\PaymentGateway;
use App\Domain\Payments\Drivers\TelrDriver;
use App\Domain\Settings\Services\SettingsRepository;
use App\Models\Setting;
use Database\Seeders\SettingsSeeder;

beforeEach(function () {
    seedPlatform();
    $this->settings = app(SettingsRepository::class);
});

it('rebuilds the mail transport from the settings table, not from env', function () {
    $this->settings->setMany([
        'mail.host' => 'smtp.example.com',
        'mail.port' => 2525,
        'mail.username' => 'postmaster',
        'mail.password' => 'a-secret',
        'mail.encryption' => 'tls',
        'mail.from_address' => 'noreply@uaeexpatwills.com',
        'mail.from_name' => 'UAE Expat Wills',
    ]);

    app(RuntimeMailer::class)->apply();

    expect(config('mail.mailers.smtp.host'))->toBe('smtp.example.com')
        ->and(config('mail.mailers.smtp.port'))->toBe(2525)
        ->and(config('mail.mailers.smtp.password'))->toBe('a-secret')
        ->and(config('mail.from.address'))->toBe('noreply@uaeexpatwills.com');
});

it('leaves the framework default alone when mail is not configured', function () {
    $before = config('mail.default');

    app(RuntimeMailer::class)->apply();

    // Half-configuring a broken transport is worse than not configuring one.
    expect(config('mail.default'))->toBe($before);
});

it('encrypts a secret setting at rest', function () {
    $this->settings->set('mail.password', 'plaintext-password');

    $stored = Setting::where('key', 'mail.password')->value('value');

    expect($stored)->not->toContain('plaintext-password')
        ->and($this->settings->get('mail.password'))->toBe('plaintext-password');
});

it('never exposes a secret setting to the browser', function () {
    $this->settings->set('payment.auth_key', 'secret-key');

    $public = $this->settings->public();

    expect($public)->not->toHaveKey('payment.auth_key')
        ->and($public)->not->toHaveKey('mail.password')
        ->and($public)->toHaveKey('branding.platform_name');
});

it('resolves the payment gateway from a setting', function () {
    $this->settings->set('payment.gateway', 'telr');

    expect(app(PaymentGateway::class))->toBeInstanceOf(TelrDriver::class)
        ->and(app(PaymentGateway::class)->name())->toBe('telr');
});

it('defaults every feature flag to false when absent', function () {
    Setting::where('key', 'features.client_portal_enabled')->delete();
    $this->settings->flush();

    // An absent or unreadable flag must never read as "on". The client area is
    // commercially gated and must not become reachable by accident.
    expect($this->settings->feature('client_portal_enabled'))->toBeFalse()
        ->and($this->settings->feature('a_flag_that_does_not_exist'))->toBeFalse();
});

it('keeps the client portal disabled by default', function () {
    expect(feature('client_portal_enabled'))->toBeFalse()
        ->and(feature('client_login_in_header'))->toBeFalse();
});

it('does not clobber an existing value when the seeder re-runs', function () {
    $this->settings->set('commercial.standard_fee', 2500);

    $this->seed(SettingsSeeder::class);

    expect($this->settings->get('commercial.standard_fee'))->toBe(2500);
});
