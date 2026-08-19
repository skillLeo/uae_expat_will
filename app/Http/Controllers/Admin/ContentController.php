<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Faq;
use App\Models\FaqCategory;
use App\Models\Page;
use App\Models\PageSection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Content management for all 13 pages, every section, and all 57 FAQs.
 *
 * Copy is Summit's, and the contract forbids altering their legal wording — so
 * this screen edits it, never rewrites it, and the preview shows exactly what
 * will ship before anything is published.
 */
class ContentController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Content/Index', [
            'pages' => Page::withCount('sections')
                ->orderBy('order')
                ->get()
                ->map(fn (Page $p) => [
                    'id' => $p->id,
                    'key' => $p->key,
                    'slug' => $p->slug,
                    'title' => $p->title,
                    'is_published' => $p->is_published,
                    'sections_count' => $p->sections_count,
                    'updated_at' => $p->updated_at->toIso8601String(),
                ]),
            'faqCount' => Faq::count(),
            'categoryCount' => FaqCategory::count(),
        ]);
    }

    public function edit(Page $page): Response
    {
        $page->load('sections');

        return Inertia::render('Admin/Content/EditPage', [
            'page' => [
                'id' => $page->id,
                'key' => $page->key,
                'slug' => $page->slug,
                'title' => $page->title,
                'seo_title' => $page->seo_title,
                'meta_description' => $page->meta_description,
                'breadcrumb' => $page->breadcrumb,
                'is_published' => $page->is_published,
                'updated_at' => $page->updated_at->toIso8601String(),
            ],
            'sections' => $page->sections->map(fn (PageSection $s) => [
                'id' => $s->id,
                'key' => $s->key,
                'order' => $s->order,
                'type' => $s->type,
                'heading' => $s->heading,
                'subheading' => $s->subheading,
                'body' => $s->body,
                'items' => $s->items,
                'settings' => $s->settings,
            ])->values(),
        ]);
    }

    public function updatePage(Request $request, Page $page): RedirectResponse
    {
        $page->update($request->validate([
            'title' => 'sometimes|string|max:200',
            'seo_title' => 'nullable|string|max:200',
            'meta_description' => 'nullable|string|max:500',
            'breadcrumb' => 'nullable|string|max:120',
            // The slug is NOT editable here. A changed URL is an SEO incident,
            // not a content change, and the routes are fixed in code.
        ]));

        return back()->with('success', 'Page details saved.');
    }

    public function updateSection(Request $request, PageSection $section): RedirectResponse
    {
        $section->update($request->validate([
            'heading' => 'nullable|string|max:1000',
            'subheading' => 'nullable|string|max:2000',
            'body' => 'nullable|string|max:100000',
            'items' => 'nullable|array',
            'settings' => 'nullable|array',
        ]));

        return back()->with('success', 'Section saved.');
    }

    public function reorderSections(Request $request, Page $page): RedirectResponse
    {
        $validated = $request->validate(['order' => 'required|array', 'order.*' => 'integer']);

        DB::transaction(function () use ($validated, $page) {
            foreach ($validated['order'] as $i => $id) {
                $page->sections()->whereKey($id)->update(['order' => ($i + 1) * 10]);
            }
        });

        return back();
    }

    public function publishPage(Request $request, Page $page): RedirectResponse
    {
        $publish = $request->boolean('is_published');

        $page->update([
            'is_published' => $publish,
            'published_at' => $publish ? now() : null,
        ]);

        activity('content')
            ->performedOn($page)
            ->causedBy($request->user('admin'))
            ->log($publish ? 'Page published' : 'Page unpublished');

        return back()->with('success', $publish ? 'Page is live.' : 'Page hidden from the public site.');
    }

    // -------------------------------------------------------------------- FAQ

    public function faqs(Request $request): Response
    {
        return Inertia::render('Admin/Content/Faqs', [
            'categories' => FaqCategory::orderBy('order')->get(['id', 'key', 'label', 'order']),
            'faqs' => Faq::orderBy('category_key')->orderBy('order')->get()
                ->map(fn (Faq $f) => [
                    'id' => $f->id,
                    'category_key' => $f->category_key,
                    'order' => $f->order,
                    'question' => $f->question,
                    'answer' => $f->answer,
                    'anchor' => $f->anchor,
                    'is_published' => $f->is_published,
                ]),
        ]);
    }

    public function storeFaq(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'category_key' => 'required|exists:faq_categories,key',
            'question' => 'required|string|max:1000',
            'answer' => 'required|string|max:20000',
            'is_published' => 'boolean',
        ]);

        Faq::create($validated + [
            'order' => ((int) Faq::where('category_key', $validated['category_key'])->max('order')) + 10,
        ]);

        return back()->with('success', 'Question added.');
    }

    public function updateFaq(Request $request, Faq $faq): RedirectResponse
    {
        $faq->update($request->validate([
            'category_key' => 'sometimes|exists:faq_categories,key',
            'question' => 'sometimes|string|max:1000',
            'answer' => 'sometimes|string|max:20000',
            'is_published' => 'boolean',
        ]));

        return back()->with('success', 'Question saved.');
    }

    public function destroyFaq(Faq $faq): RedirectResponse
    {
        $faq->delete();

        return back()->with('success', 'Question removed.');
    }

    public function reorderFaqs(Request $request): RedirectResponse
    {
        $validated = $request->validate(['order' => 'required|array', 'order.*' => 'integer']);

        DB::transaction(function () use ($validated) {
            foreach ($validated['order'] as $i => $id) {
                Faq::whereKey($id)->update(['order' => ($i + 1) * 10]);
            }
        });

        return back();
    }
}
