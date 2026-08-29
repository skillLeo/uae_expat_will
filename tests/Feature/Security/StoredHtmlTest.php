<?php

/**
 * Article bodies and page sections are rendered with v-html, so whatever is
 * stored runs in every reader's browser.
 *
 * The people who can write are trusted today, but `content.edit` is a
 * permission and a permission can be given to a coordinator, an agency or a
 * temporary account. Script stored by any of them would run for every visitor
 * and for a Super Administrator reading the same page while signed in.
 */

use App\Domain\Content\Services\HtmlSanitiser;
use App\Models\PageSection;
use App\Models\Post;

beforeEach(fn () => seedPlatform());

function cleaned(string $html): string
{
    return app(HtmlSanitiser::class)->clean($html);
}

it('removes anything that can execute', function (string $payload) {
    $out = cleaned($payload);

    expect(strtolower($out))
        ->not->toContain('<script')
        ->not->toContain('javascript:')
        ->not->toContain('onerror')
        ->not->toContain('onclick')
        ->not->toContain('onload')
        ->not->toContain('<iframe');
})->with([
    '<script>alert(1)</script>',
    '<p onclick="alert(1)">x</p>',
    '<img src=x onerror=alert(1)>',
    '<a href="javascript:alert(1)">x</a>',
    '<iframe src="https://evil.example"></iframe>',
    '<svg/onload=alert(1)>',
    '<body onload=alert(1)>x</body>',
    '<a href="  JaVaScRiPt:alert(1)">x</a>',
    '<style>body{display:none}</style>',
    '<object data="x"></object>',
]);

it('keeps the formatting a writer actually needs', function () {
    $out = cleaned(
        '<h2>Heading</h2><p>Text with <strong>bold</strong>, <em>italic</em> and '
        .'<a href="/pricing" title="Fees">a link</a>.</p><ul><li>One</li><li>Two</li></ul>'
    );

    expect($out)->toContain('<h2>Heading</h2>')
        ->toContain('<strong>bold</strong>')
        ->toContain('href="/pricing"')
        ->toContain('<li>One</li>');
});

it('keeps the words when it removes a tag it does not allow', function () {
    // Removing a stray wrapper must not delete somebody's paragraph with it.
    expect(cleaned('<div><p>Important sentence</p></div>'))
        ->toContain('Important sentence');
});

it('protects a link that opens a new tab', function () {
    expect(cleaned('<a href="https://example.com" target="_blank">x</a>'))
        ->toContain('rel="noopener noreferrer"');
});

it('survives non-latin text', function () {
    expect(cleaned('<p>وصية في الإمارات</p>'))->toContain('وصية في الإمارات');
});

it('cleans an article body on save, not on render', function () {
    $post = Post::create([
        'title' => 'Injected', 'excerpt' => 'x',
        'body' => '<p>Legitimate</p><script>alert(document.cookie)</script>',
        'author_name' => 'A', 'author_title' => 'B', 'is_published' => true,
    ]);

    // Stored clean, so nothing depends on remembering to sanitise at read time.
    expect($post->fresh()->body)
        ->toContain('Legitimate')
        ->not->toContain('<script');
});

it('cleans a page section body too', function () {
    seedContent();

    $section = PageSection::whereNotNull('body')->first();
    $section->update(['body' => '<p>Fine</p><script>alert(1)</script>']);

    expect($section->fresh()->body)->not->toContain('<script');
});
