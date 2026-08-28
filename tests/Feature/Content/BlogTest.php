<?php

/**
 * The blog exists to be found, so most of what matters here is what a search
 * engine and a reader can see: a named author, a date the piece was last
 * checked, and Article markup that carries both.
 */

use App\Models\Page;
use App\Models\Post;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    seedPlatform();
    seedContent();
});

function writePost(array $overrides = []): Post
{
    return Post::create(array_merge([
        'title' => 'Guardianship for expatriate parents in the UAE',
        'excerpt' => 'What a UAE Will can and cannot decide about who cares for your children.',
        'body' => '<p>'.str_repeat('word ', 800).'</p>',
        'author_name' => 'Dr. Mohamed Raouf',
        'author_title' => 'Principal Legal Consultant',
        'category' => 'Guardianship',
        'is_published' => true,
    ], $overrides));
}

it('lists published articles and hides drafts', function () {
    writePost(['title' => 'Published piece']);
    writePost(['title' => 'Unfinished piece', 'is_published' => false]);

    $this->get('/blog')->assertOk()->assertInertia(fn ($p) => $p
        ->has('posts.data', 1)
        ->where('posts.data.0.title', 'Published piece'));
});

it('does not show an article scheduled for the future', function () {
    writePost(['published_at' => now()->addWeek()]);

    $this->get('/blog')->assertInertia(fn ($p) => $p->has('posts.data', 0));
});

it('shows an article with its author and reading time', function () {
    $post = writePost();

    $this->get('/blog/'.$post->slug)->assertOk()->assertInertia(fn ($p) => $p
        ->where('post.author_name', 'Dr. Mohamed Raouf')
        ->where('post.author_title', 'Principal Legal Consultant')
        ->where('post.reading_minutes', 4));
});

it('does not serve a draft by its address', function () {
    $post = writePost(['is_published' => false]);

    $this->get('/blog/'.$post->slug)->assertNotFound();
});

it('carries Article markup naming the author and the review date', function () {
    // This is what a search engine reads to decide whether a legal article is
    // trustworthy and current.
    $post = writePost(['reviewed_at' => now()]);

    $this->get('/blog/'.$post->slug)->assertInertia(function ($page) {
        $graph = collect($page->toArray()['props']['structuredData']['@graph']);
        $article = $graph->firstWhere('@type', 'Article');

        expect($article['author']['name'])->toBe('Dr. Mohamed Raouf')
            ->and($article['author']['jobTitle'])->toBe('Principal Legal Consultant')
            ->and($article['dateModified'])->not->toBeNull()
            ->and($graph->firstWhere('@type', 'BreadcrumbList'))->not->toBeNull();
    });
});

it('reports the review date as the modified date, not the publication date', function () {
    $post = writePost(['published_at' => now()->subYear(), 'reviewed_at' => now()]);

    expect($post->lastVerifiedAt()->isToday())->toBeTrue()
        ->and($post->wasReviewedAfterPublishing())->toBeTrue();
});

it('resolves the fee token inside an article', function () {
    // A price typed into a post would go stale exactly like the ones that
    // already did on the pricing page and in the emails.
    $post = writePost(['body' => '<p>The professional fee is AED {fee} plus VAT.</p>']);

    $this->get('/blog/'.$post->slug)->assertInertia(fn ($p) => $p
        ->where('post.body', fn ($b) => str_contains($b, 'AED 1,999')
            && ! str_contains($b, '{fee}')));
});

it('offers related reading from the same category', function () {
    $post = writePost(['title' => 'The one being read']);
    writePost(['title' => 'Another on guardianship']);
    writePost(['title' => 'Something unrelated', 'category' => 'Pricing']);

    $this->get('/blog/'.$post->slug)->assertInertia(fn ($p) => $p
        ->has('related', 1)
        ->where('related.0.title', 'Another on guardianship'));
});

it('puts published articles in the sitemap and keeps drafts out', function () {
    $live = writePost(['title' => 'In the sitemap']);
    $draft = writePost(['title' => 'Not in it', 'is_published' => false]);

    $this->get('/sitemap.xml')->assertOk()
        ->assertSee('/blog/'.$live->slug)
        ->assertDontSee('/blog/'.$draft->slug);
});

it('keeps the blog out of the footer until something is published', function () {
    expect(collect(Page::navigation()['pages'])->pluck('href'))->not->toContain('/blog');

    writePost();
    Cache::flush();

    expect(collect(Page::navigation()['pages'])->pluck('href'))->toContain('/blog');
});

it('refuses an article with no named author', function () {
    $user = adminUser(['Super Administrator']);
    $this->actingAs($user, 'admin')->withSession(['2fa.passed' => true]);

    $this->post('/admin/content/posts', [
        'title' => 'Anonymous', 'excerpt' => 'x', 'body' => 'y',
        'author_name' => '', 'author_title' => '',
    ])->assertSessionHasErrors(['author_name', 'author_title']);
});

it('lets Summit record that an article has been checked again', function () {
    $post = writePost(['reviewed_at' => null]);
    $user = adminUser(['Super Administrator']);
    $this->actingAs($user, 'admin')->withSession(['2fa.passed' => true]);

    $this->post("/admin/content/posts/{$post->id}/reviewed")->assertSessionHasNoErrors();

    expect($post->fresh()->reviewed_at)->not->toBeNull();
});
