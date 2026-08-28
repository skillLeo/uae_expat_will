<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Models\Post;
use Illuminate\Http\Response;
use Spatie\Sitemap\Sitemap;
use Spatie\Sitemap\Tags\Url;

class SitemapController extends Controller
{
    public function index(): Response
    {
        $sitemap = Sitemap::create();

        foreach (Page::published()->orderBy('order')->get() as $page) {
            $sitemap->add(
                Url::create($page->slug === '/' ? '/' : $page->slug)
                    ->setLastModificationDate($page->updated_at)
                    ->setChangeFrequency(Url::CHANGE_FREQUENCY_MONTHLY)
                    ->setPriority($page->key === 'home' ? 1.0 : 0.7)
            );
        }

        // The assessment is a real entry point and belongs in the sitemap.
        $sitemap->add(Url::create('/assessment')->setPriority(0.9));

        if (Post::published()->exists()) {
            $sitemap->add(Url::create('/blog')->setPriority(0.7));
        }

        foreach (Post::published()->forLocale()->orderByDesc('published_at')->get() as $post) {
            $sitemap->add(
                Url::create('/blog/'.$post->slug)
                    // The review date, where there is one. It is what tells a
                    // crawler the piece has been checked since it was written.
                    ->setLastModificationDate($post->lastVerifiedAt() ?? $post->updated_at)
                    ->setChangeFrequency(Url::CHANGE_FREQUENCY_MONTHLY)
                    ->setPriority(0.6)
            );
        }

        return response($sitemap->render(), 200, ['Content-Type' => 'application/xml']);
    }

    public function robots(): Response
    {
        $lines = [
            'User-agent: *',
            // Nothing behind an assessment session, an account or an admin login
            // should ever be crawled.
            'Disallow: /admin',
            'Disallow: /client',
            'Disallow: /access',
            'Disallow: /assessment/',
            'Allow: /assessment$',
            '',
            'Sitemap: '.url('/sitemap.xml'),
        ];

        return response(implode("\n", $lines), 200, ['Content-Type' => 'text/plain']);
    }
}
