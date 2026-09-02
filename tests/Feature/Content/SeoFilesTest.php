<?php

use App\Domain\Settings\Services\SettingsRepository;

/**
 * robots.txt, the sitemap and the Search Console verification file.
 *
 * These are the three things a search engine asks for by name, and all three
 * have failed quietly at some point: a stray static public/robots.txt shadowed
 * the route on the shared host for weeks, so the sitemap was never declared
 * and the admin area was left crawlable. Nothing in the application errored.
 */
beforeEach(function () {
    seedPlatform();
    seedContent();
});

it('serves robots.txt from the route, not a static file', function () {
    // A file in public/ is served by the web server before the request reaches
    // Laravel. If this ever comes back, the assertions below stop being true.
    expect(file_exists(public_path('robots.txt')))->toBeFalse(
        'A static public/robots.txt shadows the robots route and silently undoes it.'
    );

    $this->get('/robots.txt')->assertOk();
});

it('tells search engines where the sitemap is', function () {
    $body = $this->get('/robots.txt')->assertOk()->getContent();

    expect($body)->toContain('Sitemap: '.url('/sitemap.xml'));
});

it('keeps the admin, client and access areas out of the index', function () {
    $body = $this->get('/robots.txt')->assertOk()->getContent();

    expect($body)
        ->toContain('Disallow: /admin')
        ->toContain('Disallow: /client')
        ->toContain('Disallow: /access');
});

it('lists the public pages in the sitemap, on the configured domain', function () {
    $body = $this->get('/sitemap.xml')->assertOk()
        ->assertHeader('Content-Type', 'application/xml')
        ->getContent();

    expect($body)
        ->toContain('<loc>'.rtrim(config('app.url'), '/').'</loc>')
        ->toContain('/how-it-works')
        ->toContain('/pricing')
        ->toContain('/assessment');
});

it('keeps private areas out of the sitemap', function () {
    $body = $this->get('/sitemap.xml')->assertOk()->getContent();

    expect($body)
        ->not->toContain('/admin')
        ->not->toContain('/client');
});

it('does not serve a verification file until one is configured', function () {
    $this->get('/google1a2b3c4d.html')->assertNotFound();
});

it('serves the Search Console verification file once configured', function () {
    app(SettingsRepository::class)->set('analytics.search_console_file', 'google1a2b3c4d.html');

    $this->get('/google1a2b3c4d.html')
        ->assertOk()
        ->assertSee('google-site-verification: google1a2b3c4d.html');
});

it('serves only the exact filename Google issued', function () {
    app(SettingsRepository::class)->set('analytics.search_console_file', 'google1a2b3c4d.html');

    $this->get('/google9999zzzz.html')->assertNotFound();
});

it('does not let the verification route shadow a real page', function () {
    app(SettingsRepository::class)->set('analytics.search_console_file', 'google1a2b3c4d.html');

    $this->get('/pricing')->assertOk();
    $this->get('/faqs')->assertOk();
});
