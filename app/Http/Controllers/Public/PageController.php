<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Faq;
use App\Models\FaqCategory;
use App\Models\Page;
use App\Models\PageSection;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Renders the 13 public pages from the `pages` and `page_sections` tables, so an
 * administrator's edit appears immediately with no deploy.
 */
class PageController extends Controller
{
    public function show(Request $request, string $key): Response
    {
        $page = Page::published()
            ->with('sections')
            ->where('key', $key)
            ->where('locale', app()->getLocale())
            ->firstOrFail();

        $component = match ($key) {
            'home' => 'Home',
            'faqs' => 'Faqs',
            'contact' => 'Contact',
            'terms', 'privacy', 'refund', 'disclaimer', 'cookies' => 'Legal',
            default => 'Page',
        };

        return Inertia::render($component, [
            'page' => $this->pagePayload($page, $request),
            'sections' => $this->interpolate($page->sections)->keyBy('key'),
            'structuredData' => $this->structuredData($page, $request),
            ...$this->extra($key),
        ]);
    }

    /**
     * Substitutes the commercial placeholders into stored content.
     *
     * The fee appears in a dozen sentences across the site. Storing the literal
     * "AED 2,199" in each of them would mean a price change is a dozen content
     * edits and one of them gets missed — so the copy holds {fee}, and the
     * single source of truth is the commercial settings group.
     *
     * @param  Collection<int, PageSection>  $sections
     * @return Collection<int, PageSection>
     */
    private function interpolate($sections)
    {
        $tokens = [
            '{fee}' => number_format((float) setting('commercial.standard_fee', 2199)),
            '{difc_fee}' => number_format((float) setting('commercial.difc_starting_fee', 3999)),
            '{vat_rate}' => (string) setting('commercial.vat_rate', 5),
            '{currency}' => (string) setting('commercial.currency', 'AED'),
            '{first_draft_days}' => (string) setting('commercial.first_draft_days', 2),
            '{amendment_rounds}' => (string) setting('commercial.amendment_allowance', 2),
            '{trade_licence}' => (string) setting('branding.trade_licence'),
        ];

        $walk = function ($value) use (&$walk, $tokens) {
            if (is_string($value)) {
                return strtr($value, $tokens);
            }

            if (is_array($value)) {
                return array_map($walk, $value);
            }

            return $value;
        };

        return $sections->each(function ($section) use ($walk) {
            foreach (['heading', 'subheading', 'body'] as $field) {
                if ($section->{$field} !== null) {
                    $section->{$field} = $walk($section->{$field});
                }
            }

            foreach (['items', 'settings'] as $field) {
                if ($section->{$field} !== null) {
                    $section->{$field} = $walk($section->{$field});
                }
            }
        });
    }

    /** @return array<string, mixed> */
    private function pagePayload(Page $page, Request $request): array
    {
        return [
            'key' => $page->key,
            'slug' => $page->slug,
            'title' => $page->title,
            'seo_title' => $page->seo_title,
            'meta_description' => $page->meta_description,
            'breadcrumb' => $page->breadcrumb,
            // Self-referencing canonical on every page.
            'canonical' => $request->url(),
        ];
    }

    /** @return array<string, mixed> */
    private function extra(string $key): array
    {
        return match ($key) {
            'home' => [
                // The homepage carries a short teaser set, not all 57.
                'faqs' => Faq::published()
                    ->where('category_key', 'platform')
                    ->orderBy('order')
                    ->limit(5)
                    ->get(['id', 'question', 'answer', 'anchor']),
            ],
            'faqs' => [
                'categories' => FaqCategory::orderBy('order')->get(['key', 'label']),
                // Every answer is sent, so all 57 are in the server-rendered HTML
                // even when collapsed. No pagination — a written client rule.
                'faqs' => Faq::published()
                    ->orderBy('category_key')
                    ->orderBy('order')
                    ->get(['id', 'category_key', 'question', 'answer', 'anchor']),
            ],
            default => [],
        };
    }

    /**
     * Structured data.
     *
     * Organization, WebPage/Article/Service and BreadcrumbList only.
     * FAQPage markup is deliberately NOT emitted — the client has ruled it out.
     */
    private function structuredData(Page $page, Request $request): array
    {
        $organisation = [
            '@type' => 'Organization',
            'name' => setting('branding.platform_name', 'UAE Expat Wills'),
            'url' => url('/'),
            'parentOrganization' => [
                '@type' => 'Organization',
                'name' => setting('contact.registered_entity', 'Summit Legal Consultancy UAE'),
                'identifier' => setting('branding.trade_licence'),
            ],
            'email' => setting('contact.email'),
        ];

        $graph = [
            $organisation,
            [
                '@type' => 'WebPage',
                'name' => $page->seo_title ?? $page->title,
                'description' => $page->meta_description,
                'url' => $request->url(),
            ],
            [
                '@type' => 'BreadcrumbList',
                'itemListElement' => array_values(array_filter([
                    ['@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => url('/')],
                    $page->key === 'home' ? null : [
                        '@type' => 'ListItem',
                        'position' => 2,
                        'name' => $page->breadcrumb,
                        'item' => $request->url(),
                    ],
                ])),
            ],
        ];

        if ($page->key === 'pricing') {
            $graph[] = [
                '@type' => 'Service',
                'name' => 'UAE Will preparation with human legal review',
                'provider' => $organisation,
                'areaServed' => 'AE',
            ];
        }

        return ['@context' => 'https://schema.org', '@graph' => $graph];
    }
}
