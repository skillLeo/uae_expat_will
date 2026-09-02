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

/**
 * Structured data is emitted from PublicLayout through Inertia's <Head>, whose
 * serialiser turns a vnode's props into attributes. Binding the JSON with
 * v-html therefore produced innerHTML="{...}" on an EMPTY script tag: present
 * in the HTML, ignored by every crawler, and impossible to spot without
 * reading the markup. It has to be a text child.
 */
it('emits structured data as script content, never as an attribute', function () {
    $layout = file_get_contents(resource_path('js/Layouts/PublicLayout.vue'));

    $tag = null;
    if (preg_match('/<component[^>]*application\/ld\+json.*?<\/component>/s', $layout, $m)) {
        $tag = $m[0];
    }

    expect($tag)->not->toBeNull('The ld+json script tag has gone missing from PublicLayout.');

    // v-html or innerHTML here means an empty script tag with the JSON parked
    // in an attribute. The interpolation is what makes it a text child.
    expect($tag)->not->toContain('v-html');
    expect($tag)->not->toContain('innerHTML');
    expect($tag)->toContain('{{');
});

it('escapes angle brackets in the structured data', function () {
    $layout = file_get_contents(resource_path('js/Layouts/PublicLayout.vue'));

    // A "</script>" inside any string value would otherwise close the element
    // early and spill the rest of the JSON into the document as markup.
    expect($layout)->toContain('u003C');
});
