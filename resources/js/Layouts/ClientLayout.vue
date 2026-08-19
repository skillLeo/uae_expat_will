<script setup>
/**
 * The client-area shell.
 *
 * Carries the phase-two banner on every screen, because this area is gated
 * behind Summit's written approval and nobody looking at it should be in any
 * doubt about that. Below 768px the navigation becomes a fixed bottom tab bar.
 */
import { computed } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import Wordmark from '@/Components/Wordmark.vue';

const props = defineProps({
    title: { type: String, default: 'Your matter' },
    backHref: { type: String, default: null },
    caseId: { type: [Number, String], default: null },
});

const page = usePage();

const tabs = computed(() => {
    const id = props.caseId;
    return [
        { label: 'Matters', href: '/client', icon: 'folder' },
        ...(id ? [
            { label: 'Questionnaire', href: `/client/cases/${id}/questionnaire`, icon: 'list' },
            { label: 'Documents', href: `/client/cases/${id}/documents`, icon: 'doc' },
            { label: 'Drafts', href: `/client/cases/${id}/drafts`, icon: 'draft' },
        ] : []),
    ];
});

const ICONS = {
    folder: 'M2.5 5.5A1.5 1.5 0 0 1 4 4h3.5l1.5 2H16a1.5 1.5 0 0 1 1.5 1.5v7A1.5 1.5 0 0 1 16 16H4a1.5 1.5 0 0 1-1.5-1.5z',
    list: 'M4 5h12M4 10h12M4 15h12',
    doc: 'M5 2.5h7l3 3v12H5zM12 2.5v3h3',
    draft: 'M4 16l1-4 8-8 3 3-8 8z',
};

const isActive = (href) => href === '/client' ? page.url === '/client' : page.url.startsWith(href);
const logout = () => router.post('/client/logout');
</script>

<template>
    <Head :title="title">
        <meta name="robots" content="noindex, nofollow" />
    </Head>

    <div class="flex min-h-dvh flex-col bg-page">
        <!-- The phase-two banner. On every screen, deliberately. -->
        <div class="bg-held px-6 py-2.5 max-[719px]:px-4">
            <div class="mx-auto flex max-w-[1280px] flex-wrap items-center gap-x-4 gap-y-1">
                <span class="pill border-held-border bg-held-bg text-held">Phase two</span>
                <p class="text-caption leading-[1.5] text-paper">
                    The client area is built but not yet released. It becomes available once Summit
                    approves this phase in writing.
                </p>
            </div>
        </div>

        <header class="on-ink sticky top-0 z-30 border-b border-ink-line bg-ink-deep">
            <div class="mx-auto flex max-w-[1280px] items-center gap-4 px-6 py-3.5 max-[719px]:px-4">
                <Link v-if="backHref" :href="backHref" class="tap -ml-2 grid place-items-center px-2 text-steel hover:text-paper" aria-label="Back">
                    <svg width="20" height="20" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true">
                        <polyline points="12,4 6,10 12,16" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                </Link>
                <Link v-else href="/client" class="flex-none"><Wordmark context="header" ground="ink" /></Link>

                <h1 class="min-w-0 flex-1 truncate text-body font-medium text-paper max-[719px]:text-body-s">{{ title }}</h1>

                <nav class="flex items-center gap-4 max-[767px]:hidden" aria-label="Client area">
                    <Link
                        v-for="t in tabs" :key="t.href" :href="t.href"
                        class="text-body-s" :class="isActive(t.href) ? 'text-gold-soft' : 'text-steel hover:text-paper'"
                    >{{ t.label }}</Link>
                </nav>

                <button type="button" class="tap flex-none text-body-s text-steel hover:text-paper" @click="logout">Sign out</button>
            </div>
        </header>

        <main id="main" class="flex-1 px-6 py-8 pb-28 max-[719px]:px-4 max-[719px]:py-6 max-[719px]:pb-28">
            <div class="mx-auto max-w-[1280px]"><slot /></div>
        </main>

        <div v-if="$slots.actions" class="z-actionbar safe-bottom fixed inset-x-0 bottom-0 border-t border-rule-cool bg-surface shadow-raised max-[767px]:bottom-[64px]">
            <div class="mx-auto flex max-w-[1280px] items-center gap-3 px-6 py-3 max-[719px]:px-4">
                <slot name="actions" />
            </div>
        </div>

        <!-- Bottom tab bar, below 768px. -->
        <nav v-if="tabs.length > 1" class="z-tabbar safe-bottom fixed inset-x-0 bottom-0 hidden border-t border-ink-line bg-ink-deep max-[767px]:block" aria-label="Client area">
            <div class="grid" :style="{ gridTemplateColumns: `repeat(${tabs.length}, minmax(0, 1fr))` }">
                <Link
                    v-for="t in tabs" :key="t.href" :href="t.href"
                    class="tap flex flex-col items-center justify-center gap-1 py-2 text-[11px]"
                    :class="isActive(t.href) ? 'text-gold-soft' : 'text-steel'"
                    :aria-current="isActive(t.href) ? 'page' : undefined"
                >
                    <svg width="20" height="20" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path :d="ICONS[t.icon]" />
                    </svg>
                    {{ t.label }}
                </Link>
            </div>
        </nav>
    </div>
</template>
