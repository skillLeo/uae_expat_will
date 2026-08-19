<?php

use App\Domain\Settings\Services\SettingsRepository;
use App\Models\Setting;
use App\Models\User;

beforeEach(fn () => seedPlatform());

$routes = [
    '/client',
    '/client/login',
    '/client/register',
    '/client/magic-link',
    '/client/forgot-password',
    '/access/some-token',
];

it('404s every client route while the flag is off', function (string $route) {
    expect(feature('client_portal_enabled'))->toBeFalse();

    // A 404 and not a 302 or 403: a redirect to sign-in confirms the area
    // exists, and an unapproved phase must not be advertised.
    $this->get($route)->assertNotFound();
})->with($routes);

it('404s for a signed-in customer too', function (string $route) {
    $customer = User::factory()->create(['user_type' => User::TYPE_CUSTOMER]);
    $this->actingAs($customer, 'web');

    $this->get($route)->assertNotFound();
})->with($routes);

it('opens the client routes once the flag is on', function () {
    app(SettingsRepository::class)->set('features.client_portal_enabled', true);

    $this->get('/client/login')->assertOk();
    $this->get('/client/register')->assertOk();

    // Still requires authentication — the flag opens the area, it does not
    // remove the auth gate.
    $this->get('/client')->assertRedirect();
});

it('treats an absent flag as off', function () {
    Setting::where('key', 'features.client_portal_enabled')->delete();
    app(SettingsRepository::class)->flush();

    expect(feature('client_portal_enabled'))->toBeFalse();
    $this->get('/client/login')->assertNotFound();
});
