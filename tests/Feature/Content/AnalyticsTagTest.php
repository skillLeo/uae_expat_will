<?php

use App\Domain\Settings\Services\SettingsRepository;

/**
 * The Google tag, and the consent it starts with.
 *
 * The tag is emitted on every page so Google's own "Test installation" check
 * can find it — a script withheld until someone clicks Accept cannot be, and
 * reads as a broken install when it is not. What protects the visitor is not
 * the script's absence but Consent Mode: it starts with every permission
 * denied, so no analytics cookie and no identifier exists until they accept.
 *
 * If the default ever stops being "denied", the site silently starts tracking
 * people who have not agreed, on a platform where visitors are researching
 * their own death. These tests exist for that line specifically.
 */
beforeEach(function () {
    seedPlatform();
    seedContent();
});

it('emits the Google tag when a measurement ID is configured', function () {
    app(SettingsRepository::class)->set('analytics.ga4_measurement_id', 'G-TESTID123');

    $this->get('/')->assertOk()
        ->assertSee('googletagmanager.com/gtag/js?id=G-TESTID123', false)
        ->assertSee('G-TESTID123', false);
});

it('starts with every consent denied', function () {
    app(SettingsRepository::class)->set('analytics.ga4_measurement_id', 'G-TESTID123');

    $body = $this->get('/')->assertOk()->getContent();

    expect($body)->toContain("gtag('consent', 'default'");
    foreach (['ad_storage', 'ad_user_data', 'ad_personalization', 'analytics_storage'] as $key) {
        expect($body)->toMatch("/{$key}:\s*'denied'/");
    }
});

it('never turns on Google signals or full IP logging', function () {
    app(SettingsRepository::class)->set('analytics.ga4_measurement_id', 'G-TESTID123');

    $body = $this->get('/')->assertOk()->getContent();

    expect($body)
        ->toContain('anonymize_ip: true')
        ->toContain('allow_google_signals: false');
});

it('emits nothing at all when no measurement ID is set', function () {
    app(SettingsRepository::class)->set('analytics.ga4_measurement_id', '');

    $this->get('/')->assertOk()
        ->assertDontSee('googletagmanager.com', false)
        ->assertDontSee("gtag('consent'", false);
});

it('opens the analytics hosts in the CSP only when a tag is configured', function () {
    app(SettingsRepository::class)->set('analytics.ga4_measurement_id', '');
    $csp = $this->get('/')->assertOk()->headers->get('Content-Security-Policy');
    expect($csp)->not->toContain('googletagmanager.com');

    app(SettingsRepository::class)->set('analytics.ga4_measurement_id', 'G-TESTID123');
    $csp = $this->get('/')->assertOk()->headers->get('Content-Security-Policy');
    expect($csp)
        ->toContain('https://www.googletagmanager.com')
        ->toContain('https://www.google-analytics.com');
});

it('grants analytics storage only through a consent update, never by default', function () {
    // The banner is the only thing that may move consent from denied to
    // granted, and it does so with an update call rather than by injecting a
    // second tag.
    $banner = file_get_contents(resource_path('js/Components/CookieConsent.vue'));

    expect($banner)
        ->toContain("gtag('consent', 'update'")
        ->toContain("prefs.analytics ? 'granted' : 'denied'");

    // Marketing storage is refused outright, whatever the visitor picks.
    expect($banner)->toContain("ad_storage: 'denied'");
});
