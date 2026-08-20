<?php

use Illuminate\Support\Facades\Route;

beforeEach(function () {
    seedPlatform();
    // Debug off is what production looks like, and what makes the handler
    // render a real page instead of a stack trace.
    config(['app.debug' => false]);
});

it('renders a real 404 page for an unmatched URL', function () {
    // A 404 matches no route, so the web middleware group never runs: there is
    // no session, no shared props and no Ziggy. This has broken twice.
    $this->get('/this-page-does-not-exist')
        ->assertNotFound()
        ->assertInertia(fn ($page) => $page->component('Error')->where('status', 404));
});

it('does not throw while building the 404 response', function () {
    $response = $this->get('/no-such-page');

    expect($response->status())->toBe(404)
        ->and($response->getContent())->not->toContain('Session store not set');
});

it('carries the settings the layout needs onto an error response', function () {
    $response = $this->get('/no-such-page');

    // Setting keys contain dots, which the Inertia assertion helper reads as
    // nested paths, so inspect the decoded payload directly.
    $props = $response->viewData('page')['props'];

    expect($props['settings'])->toBeArray()
        ->and($props['settings']['branding.ownership_line'])->toContain('4429232.01')
        ->and($props['auth']['user'])->toBeNull();
});

it('renders a 500 page with a reference for the client to quote', function () {
    Route::get('/__boom', fn () => throw new RuntimeException('deliberate'))->middleware('web');

    $this->get('/__boom')
        ->assertStatus(500)
        ->assertInertia(fn ($page) => $page->component('Error')
            ->where('status', 500)
            ->where('reference', fn ($r) => str_starts_with((string) $r, 'ERR-')));
});

it('renders a 403 page', function () {
    Route::get('/__forbidden', fn () => abort(403))->middleware('web');

    $this->get('/__forbidden')
        ->assertForbidden()
        ->assertInertia(fn ($page) => $page->component('Error')->where('status', 403));
});

it('leaves JSON requests as JSON', function () {
    $this->getJson('/no-such-page')->assertNotFound()->assertJsonStructure(['message']);
});

it('shows the stack trace instead when debug is on', function () {
    config(['app.debug' => true]);

    // A developer wants the trace, not a polite page.
    $this->get('/no-such-page')->assertNotFound();
})->throwsNoExceptions();
