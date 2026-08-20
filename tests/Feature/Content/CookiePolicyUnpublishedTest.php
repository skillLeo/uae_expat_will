<?php

use App\Models\Page;

beforeEach(function () {
    seedPlatform();
    seedContent();
});

it('keeps the cookie policy unpublished', function () {
    // The specification forbids publishing it before the production cookie scan.
    expect(Page::where('key', 'cookies')->value('is_published'))->toBeFalse();
});

it('serves a proper not-found page rather than an error', function () {
    $this->get('/cookie-policy')->assertNotFound();
});

it('keeps the content so it can be republished in one click', function () {
    $page = Page::where('key', 'cookies')->with('sections')->first();

    expect($page->sections)->not->toBeEmpty()
        ->and($page->meta_description)->not->toBeEmpty();
});

it('leaves it out of the footer while it is unpublished', function () {
    $legal = collect(Page::navigation()['legal']);

    expect($legal->pluck('href'))->not->toContain('/cookie-policy')
        // The four that are published still appear.
        ->and($legal)->toHaveCount(4);
});

it('puts the link back the moment it is republished', function () {
    Page::where('key', 'cookies')->first()->update(['is_published' => true]);

    expect(collect(Page::navigation()['legal'])->pluck('href'))->toContain('/cookie-policy');
});

it('keeps it out of the sitemap', function () {
    $this->get('/sitemap.xml')
        ->assertOk()
        ->assertDontSee('/cookie-policy');
});

it('ships the footer links on error pages too', function () {
    // Error responses are built outside the Inertia middleware, so the footer
    // gets its links from a separate share that has to stay in step.
    config(['app.debug' => false]);

    $this->get('/no-such-page')
        ->assertNotFound()
        ->assertInertia(fn ($page) => $page->has('navigation.legal', 4));
});

it('still lets a visitor change their cookie choices', function () {
    // The consent panel is the mechanism for withdrawing consent, and it must
    // keep working without the policy page.
    $this->postJson('/consent/cookie', [
        'choice' => 'manage_preferences',
        'preferences' => ['prefs' => true, 'analytics' => false, 'marketing' => false],
        'version' => '2026-08-20',
    ])->assertOk()->assertJson(['recorded' => true]);
});
