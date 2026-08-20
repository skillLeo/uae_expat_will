<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SavedView;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SavedViewController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:80',
            'resource' => 'required|string|max:40',
            'filters' => 'array',
            'is_shared' => 'boolean',
        ]);

        // Sharing a view puts it in front of the whole team, so it needs the
        // permission that means "you see every case anyway".
        $shared = ($validated['is_shared'] ?? false) && $request->user('admin')->can('cases.view.all');

        SavedView::create([
            'user_id' => $request->user('admin')->id,
            'name' => $validated['name'],
            'resource' => $validated['resource'],
            'filters' => array_filter($validated['filters'] ?? [], fn ($v) => $v !== null && $v !== ''),
            'is_shared' => $shared,
        ]);

        return back()->with('success', "View \"{$validated['name']}\" saved.");
    }

    public function destroy(Request $request, SavedView $savedView): RedirectResponse
    {
        // You can delete your own, or a shared one if you can manage cases.
        abort_unless(
            $savedView->user_id === $request->user('admin')->id
                || ($savedView->is_shared && $request->user('admin')->can('cases.view.all')),
            403,
        );

        $savedView->delete();

        return back()->with('success', 'View removed.');
    }
}
