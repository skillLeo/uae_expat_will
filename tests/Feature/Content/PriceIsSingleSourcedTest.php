<?php

/**
 * The price appears in page copy, FAQ answers, two legal clauses, three
 * notification templates, a meta description and structured data. It went
 * wrong once already — the site advertised one figure while the Terms promised
 * another — so this test exists to make sure it can only ever come from one
 * place.
 */

use App\Domain\Settings\Services\SettingsRepository;

beforeEach(function () {
    seedPlatform();
    seedContent();
});

it('renders the fee from settings everywhere it appears on a page', function () {
    app(SettingsRepository::class)->set('commercial.standard_fee', 1234);

    $this->get('/pricing')
        ->assertOk()
        ->assertInertia(fn ($p) => $p
            // The meta description is what search engines index. A stale price
            // here is a wrong price in the search results.
            ->where('page.meta_description', fn ($d) => str_contains($d, 'AED 1,234'))
            ->where('structuredData.@graph.1.description', fn ($d) => str_contains($d, 'AED 1,234')));
});

it('renders the fee from settings on how-it-works and uae-will-options too', function () {
    // Both of these once carried the fee as a hand-typed literal in
    // pages.json rather than the {fee} token, so the price drifted silently
    // while every other page updated. This guards both routes directly.
    app(SettingsRepository::class)->set('commercial.standard_fee', 1234);

    foreach (['/how-it-works', '/uae-will-registration-options'] as $url) {
        $this->get($url)->assertOk()->assertInertia(function ($page) {
            $rendered = json_encode($page->toArray()['props']['sections'] ?? []);

            expect($rendered)->toContain('AED 1,234')
                ->and($rendered)->not->toContain('{fee}')
                ->and($rendered)->not->toContain('2,199');
        });
    }
});

it('resolves the {fee} token in FAQ answers instead of leaking it raw', function () {
    // FAQ answers come off the model directly rather than through
    // PageController::interpolate(), so they need their own token pass —
    // this once meant the FAQ page showed the literal text "{fee}".
    app(SettingsRepository::class)->set('commercial.standard_fee', 1234);

    $this->get('/faqs')->assertOk()->assertInertia(function ($page) {
        $rendered = json_encode($page->toArray()['props']['faqs'] ?? []);

        expect($rendered)->toContain('AED 1,234')
            ->and($rendered)->not->toContain('{fee}');
    });
});

it('keeps the legal clauses in step with the advertised price', function () {
    app(SettingsRepository::class)->set('commercial.standard_fee', 1234);

    foreach (['/terms-and-conditions', '/payment-and-refund-policy'] as $url) {
        $this->get($url)->assertOk()->assertInertia(function ($page) {
            $rendered = json_encode($page->toArray()['props']['sections'] ?? []);

            expect($rendered)->toContain('AED 1,234')
                ->and($rendered)->not->toContain('2,199');
        });
    }
});

it('leaves no price written out by hand in the seeded content', function () {
    // The whole class of bug: a literal that no setting can reach.
    // pages.json is included because that's exactly where it happened —
    // "AED 2,199" sat there, hand-typed, untouched by three price changes.
    foreach (['content.json', 'notification_templates.json', 'pages.json'] as $file) {
        $raw = file_get_contents(database_path('seeders/data/'.$file));

        expect($raw)->not->toContain('2,199')
            ->and($raw)->not->toContain('1,999');
    }

    expect(file_get_contents(database_path('seeders/ContentSeeder.php')))
        ->not->toContain('AED 2,199');
});
