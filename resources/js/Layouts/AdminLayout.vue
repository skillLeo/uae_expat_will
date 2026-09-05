<script setup>
/**
 * The admin shell.
 *
 * A 232px rail plus fluid main on wide screens. Below 768px the rail becomes a
 * fixed bottom tab bar with safe-area padding, and the top bar carries a
 * contextual back, a title and one action — so it reads like an app rather than
 * a shrunken website.
 */
import { ref, computed } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import Wordmark from '@/Components/Wordmark.vue';

const props = defineProps({
    title: { type: String, default: 'Admin' },
    backHref: { type: String, default: null },
});

const page = usePage();
const menuOpen = ref(false);

const permissions = computed(() => page.props.auth?.permissions ?? []);
const can = (p) => permissions.value.includes(p);

const nav = computed(() => [
    { label: 'Dashboard', href: '/admin', icon: 'grid', show: true },
    { label: 'Cases', href: '/admin/cases', icon: 'folder', show: can('cases.view.all') || can('cases.view.assigned') },
    { label: 'Payments', href: '/admin/payments', icon: 'card', show: can('payments.view') },
    { label: 'Content', href: '/admin/content', icon: 'doc', show: can('content.view') },
    // Its own entry rather than a card inside Content. It was reachable only
    // by knowing the blog lived under "Content", which nobody does, and the
    // client reasonably reported it as missing.
    { label: 'Blog', href: '/admin/content/posts', icon: 'pen', show: can('content.view') },
    { label: 'Questionnaire', href: '/admin/questionnaire', icon: 'list', show: can('questionnaire.view') },
    { label: 'Users', href: '/admin/users', icon: 'people', show: can('users.view') },
    { label: 'Settings', href: '/admin/settings', icon: 'cog', show: can('settings.view') },
    { label: 'Audit log', href: '/admin/audit', icon: 'shield', show: can('audit.view') },
].filter((i) => i.show));

// The bottom bar holds at most five — more than that and the targets shrink
// below 46px on a small phone.
const tabs = computed(() => nav.value.slice(0, 5));

/**
 * The most specific entry wins.
 *
 * A plain startsWith lights up Content as well as Blog whenever the blog is
 * open, because /admin/content/posts starts with /admin/content. Two
 * highlighted items is a small thing that makes the navigation feel broken.
 */
const isActive = (href) => {
    if (href === '/admin') return page.url === '/admin';
    if (!page.url.startsWith(href)) return false;

    return !nav.value.some(
        (item) => item.href !== href
            && item.href.length > href.length
            && page.url.startsWith(item.href),
    );
};

const ICONS = {
    grid: 'M3 3h6v6H3zM11 3h6v6h-6zM3 11h6v6H3zM11 11h6v6h-6z',
    folder: 'M2.5 5.5A1.5 1.5 0 0 1 4 4h3.5l1.5 2H16a1.5 1.5 0 0 1 1.5 1.5v7A1.5 1.5 0 0 1 16 16H4a1.5 1.5 0 0 1-1.5-1.5z',
    card: 'M2.5 5.5h15v9h-15zM2.5 8.5h15',
    doc: 'M5 2.5h7l3 3v12H5zM12 2.5v3h3',
    list: 'M4 5h12M4 10h12M4 15h12',
    people: 'M7 9a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5M2.5 16c0-2.5 2-4 4.5-4s4.5 1.5 4.5 4M13 12c2 .4 3.5 1.8 3.5 4',
    cog: 'M10 12.5a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5M10 2.5v2M10 15.5v2M17.5 10h-2M4.5 10h-2',
    shield: 'M10 2.5 16 5v5c0 4-3 6.5-6 7.5-3-1-6-3.5-6-7.5V5z',
    pen: 'M13.5 3.5 16.5 6.5 7 16H4v-3zM11.5 5.5 14.5 8.5',
};

const logout = () => router.post('/admin/logout');
</script>

<template>
    <Head :title="title" />

    <div class="min-h-dvh bg-paper-sunk">
        <div class="grid min-h-dvh grid-cols-[232px_minmax(0,1fr)] max-[1080px]:grid-cols-1">
            <!-- Rail -->
            <aside class="on-ink sticky top-0 h-dvh overflow-y-auto border-r border-ink-line bg-ink-deep max-[1080px]:hidden">
                <div class="border-b border-ink-line p-6">
                    <Link href="/admin"><Wordmark context="header" ground="ink" /></Link>
                </div>
                <nav class="grid gap-1 p-3" aria-label="Admin">
                    <Link
                        v-for="item in nav" :key="item.href" :href="item.href"
                        class="tap flex items-center gap-3 rounded-sm px-3 text-body-s"
                        :class="isActive(item.href) ? 'bg-ink text-paper' : 'text-steel hover:bg-ink hover:text-paper'"
                        :aria-current="isActive(item.href) ? 'page' : undefined"
                    >
                        <svg width="18" height="18" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round" class="flex-none" aria-hidden="true">
                            <path :d="ICONS[item.icon]" />
                        </svg>
                        {{ item.label }}
                    </Link>
                </nav>
                <div class="border-t border-ink-line p-3">
                    <div class="px-3 pb-2 text-caption text-steel">{{ page.props.auth?.user?.name }}</div>
                    <button type="button" class="tap w-full rounded-sm px-3 text-left text-body-s text-steel hover:bg-ink hover:text-paper" @click="logout">
                        Sign out
                    </button>
                </div>
            </aside>

            <!-- Main -->
            <div class="flex min-w-0 flex-col">
                <!-- Top bar: contextual back, title, one action. -->
                <header class="sticky top-0 z-30 border-b border-rule-cool bg-surface">
                    <div class="flex items-center gap-3 px-8 py-3 max-[719px]:px-4">
                        <Link v-if="backHref" :href="backHref" class="tap -ml-2 grid place-items-center rounded-sm px-2 text-slate hover:text-ink" aria-label="Back">
                            <svg width="20" height="20" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true">
                                <polyline points="12,4 6,10 12,16" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                        </Link>
                        <h1 class="min-w-0 flex-1 truncate text-h4 font-semibold text-ink">{{ title }}</h1>
                        <slot name="action" />
                    </div>
                </header>

                <main id="main" class="flex-1 p-8 pb-28 max-[719px]:p-4 max-[719px]:pb-28">
                    <slot />
                </main>
            </div>
        </div>

        <!-- Bottom tab bar, below 768px only. -->
        <nav
            class="z-tabbar safe-bottom fixed inset-x-0 bottom-0 hidden border-t border-ink-line bg-ink-deep max-[767px]:block"
            aria-label="Admin"
        >
            <div class="grid" :style="{ gridTemplateColumns: `repeat(${tabs.length}, minmax(0, 1fr))` }">
                <Link
                    v-for="item in tabs" :key="item.href" :href="item.href"
                    class="tap flex flex-col items-center justify-center gap-1 py-2 text-[11px]"
                    :class="isActive(item.href) ? 'text-gold-soft' : 'text-steel'"
                    :aria-current="isActive(item.href) ? 'page' : undefined"
                >
                    <svg width="20" height="20" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path :d="ICONS[item.icon]" />
                    </svg>
                    {{ item.label }}
                </Link>
            </div>
        </nav>
    </div>
</template>
