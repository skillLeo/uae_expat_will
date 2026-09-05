<?php

/**
 * The blog, findable from the admin navigation.
 *
 * It was built, routed and working, but the only way in was to open Content
 * and then click a card inside it. Nobody knows a blog lives under "Content",
 * so the client looked at the sidebar, saw no Blog, and reported it missing.
 * A feature nobody can find is not a feature.
 */
beforeEach(function () {
    seedPlatform();
    seedContent();
});

it('has its own entry in the admin navigation', function () {
    $layout = file_get_contents(resource_path('js/Layouts/AdminLayout.vue'));

    expect($layout)
        ->toContain("label: 'Blog'")
        ->toContain("href: '/admin/content/posts'");
});

it('opens for anyone who can see content', function () {
    $user = adminUser();
    $this->actingAs($user, 'admin')->withSession(['2fa.passed' => true]);

    $this->get('/admin/content/posts')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Admin/Content/Posts'));
});

it('does not light up two navigation entries at once', function () {
    // /admin/content/posts starts with /admin/content, so a plain startsWith
    // highlights Content as well as Blog and the navigation looks broken.
    $layout = file_get_contents(resource_path('js/Layouts/AdminLayout.vue'));

    expect($layout)->toContain('item.href.length > href.length');
});

it('is refused to someone without content permission', function () {
    $user = adminUser(['Finance']);
    $this->actingAs($user, 'admin')->withSession(['2fa.passed' => true]);

    $this->get('/admin/content/posts')->assertForbidden();
});
