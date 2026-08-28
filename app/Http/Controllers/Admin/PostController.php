<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Post;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Blog administration.
 *
 * Summit writes their own articles. The platform stores them, dates them and
 * puts the author's name on them; it does not draft legal content, which is
 * the same rule that applies to every other piece of wording on this site.
 */
class PostController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Content/Posts', [
            'posts' => Post::orderByDesc('published_at')->orderByDesc('id')->get()
                ->map(fn (Post $p) => [
                    'id' => $p->id,
                    'title' => $p->title,
                    'slug' => $p->slug,
                    'category' => $p->category,
                    'author_name' => $p->author_name,
                    'is_published' => $p->is_published,
                    'published_at' => $p->published_at?->toIso8601String(),
                    'reviewed_at' => $p->reviewed_at?->toIso8601String(),
                    'reading_minutes' => $p->reading_minutes,
                    'url' => $p->url(),
                ]),
            // Offered as suggestions rather than a fixed list, so a writer is
            // not blocked by a category nobody thought of.
            'categories' => Post::query()->whereNotNull('category')
                ->distinct()->orderBy('category')->pluck('category'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Post::create($this->validated($request));

        return back()->with('success', 'Article created.');
    }

    public function update(Request $request, Post $post): RedirectResponse
    {
        $post->update($this->validated($request, $post));

        return back()->with('success', 'Article saved.');
    }

    public function destroy(Post $post): RedirectResponse
    {
        $post->delete();

        return back()->with('success', 'Article deleted. It can be restored from the database if needed.');
    }

    /**
     * Records that the article has been checked and is still correct.
     *
     * A separate action from editing, because on a subject where the law moves
     * "we read this again and it still stands" is a real event worth dating,
     * and it should not require pretending to change something.
     */
    public function markReviewed(Post $post): RedirectResponse
    {
        $post->update(['reviewed_at' => now()]);

        activity('content')
            ->performedOn($post)
            ->causedBy(request()->user('admin'))
            ->log('Article reviewed');

        return back()->with('success', 'Marked as reviewed today.');
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?Post $post = null): array
    {
        return $request->validate([
            'title' => 'required|string|max:190',
            'slug' => [
                'nullable', 'string', 'max:190', 'regex:/^[a-z0-9-]+$/',
                'unique:posts,slug'.($post ? ','.$post->id : ''),
            ],
            'category' => 'nullable|string|max:60',
            // Both are shown to a reader, so neither may be empty. The excerpt
            // doubles as the meta description when none is given.
            'excerpt' => 'required|string|max:400',
            'body' => 'required|string',
            'author_name' => 'required|string|max:120',
            'author_title' => 'required|string|max:120',
            'seo_title' => 'nullable|string|max:190',
            'meta_description' => 'nullable|string|max:320',
            'is_published' => 'boolean',
            'published_at' => 'nullable|date',
            'reviewed_at' => 'nullable|date',
        ], [
            'slug.regex' => 'The web address may use lower-case letters, numbers and hyphens only.',
            'author_name.required' => 'An article needs a named author.',
            'author_title.required' => 'An author needs a title, so a reader can judge the source.',
        ]);
    }
}
