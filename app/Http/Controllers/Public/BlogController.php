<?php

namespace App\Http\Controllers\Public;

use App\Domain\Settings\Services\CommercialTokens;
use App\Http\Controllers\Controller;
use App\Models\Post;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The blog.
 *
 * Server-rendered like every other public page, because the entire point of it
 * is to be found. Structured data is Article rather than the FAQPage markup
 * the client has ruled out elsewhere, and it carries the author and the review
 * date — on a subject that decides what happens to somebody's family, a page
 * with no named author and no date is worth very little.
 */
class BlogController extends Controller
{
    public function index(Request $request): Response
    {
        $posts = Post::published()->forLocale()
            ->orderByDesc('published_at')
            ->paginate(12)
            ->withQueryString()
            ->through(fn (Post $p) => $this->card($p));

        return Inertia::render('Blog/Index', [
            'posts' => $posts,
            'page' => [
                'title' => 'Insights',
                'seo_title' => 'UAE Wills and Inheritance — Insights from Summit Legal Consultancy',
                'meta_description' => 'Practical writing on UAE Wills, registration routes, guardianship '
                    .'and inheritance, from the legal team at Summit Legal Consultancy.',
                'canonical' => $request->url(),
            ],
            'structuredData' => $this->indexStructuredData($request),
        ]);
    }

    public function show(Request $request, string $slug): Response
    {
        $post = Post::published()->forLocale()->where('slug', $slug)->firstOrFail();

        $tokens = app(CommercialTokens::class);

        return Inertia::render('Blog/Show', [
            'post' => [
                'title' => $post->title,
                'category' => $post->category,
                'excerpt' => $tokens->apply($post->excerpt),
                // The fee can appear in a post the same way it appears
                // anywhere else, and must never be typed in by hand.
                'body' => $tokens->apply($post->body),
                'author_name' => $post->author_name,
                'author_title' => $post->author_title,
                'published_at' => $post->published_at?->toIso8601String(),
                'reviewed_at' => $post->reviewed_at?->toIso8601String(),
                'was_reviewed' => $post->wasReviewedAfterPublishing(),
                'reading_minutes' => $post->reading_minutes,
            ],
            'page' => [
                'title' => $post->title,
                'seo_title' => $tokens->apply($post->seo_title ?? $post->title),
                'meta_description' => $tokens->apply($post->meta_description ?? $post->excerpt),
                'canonical' => $post->url(),
            ],
            // Related reading keeps somebody on the site, and is the only
            // reason a blog earns its place beside a set of FAQs.
            'related' => Post::published()->forLocale()
                ->whereKeyNot($post->id)
                ->when($post->category, fn ($q) => $q->where('category', $post->category))
                ->orderByDesc('published_at')
                ->limit(3)
                ->get()
                ->map(fn (Post $p) => $this->card($p))
                ->values(),
            'structuredData' => $this->articleStructuredData($post, $request),
        ]);
    }

    /** @return array<string, mixed> */
    private function card(Post $post): array
    {
        return [
            'slug' => $post->slug,
            'title' => $post->title,
            'excerpt' => $post->excerpt,
            'category' => $post->category,
            'author_name' => $post->author_name,
            'published_at' => $post->published_at?->toIso8601String(),
            'reviewed_at' => $post->reviewed_at?->toIso8601String(),
            'reading_minutes' => $post->reading_minutes,
        ];
    }

    /** @return array<string, mixed> */
    private function organisation(): array
    {
        return [
            '@type' => 'Organization',
            'name' => setting('branding.platform_name', 'UAE Expat Wills'),
            'url' => url('/'),
            'parentOrganization' => [
                '@type' => 'Organization',
                'name' => setting('contact.registered_entity', 'Summit Legal Consultancy UAE'),
                'identifier' => setting('branding.trade_licence'),
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function indexStructuredData(Request $request): array
    {
        return [
            '@context' => 'https://schema.org',
            '@graph' => [
                $this->organisation(),
                [
                    '@type' => 'Blog',
                    'name' => 'UAE Expat Wills Insights',
                    'url' => $request->url(),
                    'publisher' => $this->organisation(),
                ],
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function articleStructuredData(Post $post, Request $request): array
    {
        return [
            '@context' => 'https://schema.org',
            '@graph' => [
                $this->organisation(),
                array_filter([
                    '@type' => 'Article',
                    'headline' => $post->title,
                    'description' => $post->meta_description ?? $post->excerpt,
                    'datePublished' => $post->published_at?->toIso8601String(),
                    // Search engines read this as "is it still current", which
                    // is the whole reason the column exists.
                    'dateModified' => $post->lastVerifiedAt()?->toIso8601String(),
                    'author' => [
                        '@type' => 'Person',
                        'name' => $post->author_name,
                        'jobTitle' => $post->author_title,
                        'worksFor' => $this->organisation(),
                    ],
                    'publisher' => $this->organisation(),
                    'mainEntityOfPage' => $post->url(),
                    'inLanguage' => $post->locale,
                ]),
                [
                    '@type' => 'BreadcrumbList',
                    'itemListElement' => [
                        ['@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => url('/')],
                        ['@type' => 'ListItem', 'position' => 2, 'name' => 'Insights', 'item' => url('/blog')],
                        ['@type' => 'ListItem', 'position' => 3, 'name' => $post->title, 'item' => $post->url()],
                    ],
                ],
            ],
        ];
    }
}
