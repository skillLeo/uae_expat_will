<script setup>
/**
 * The assessment shell.
 *
 * A sticky context header on ink, the question on page ground, and the primary
 * action bottom-anchored rather than scrolled to. On a phone the action bar sits
 * above the keyboard and above the safe area, and the WhatsApp control suppresses
 * itself while it is present — the three fixed elements never overlap.
 */
import { Head, Link } from '@inertiajs/vue3';
import Wordmark from '@/Components/Wordmark.vue';
import ProgressStages from '@/Components/ProgressStages.vue';

defineProps({
    title: { type: String, default: 'Assessment' },
    progress: { type: Object, default: null },
});
</script>

<template>
    <Head>
        <title>{{ title }}</title>
        <!-- The assessment must never be indexed: its URLs are session-scoped. -->
        <meta name="robots" content="noindex, nofollow" />
    </Head>

    <div class="flex min-h-dvh flex-col bg-page">
        <header class="on-ink sticky top-0 z-30 border-b border-ink-line bg-ink-deep">
            <div class="mx-auto flex max-w-[1216px] items-center justify-between gap-4 px-8 py-3.5 max-[719px]:px-4">
                <Link href="/" aria-label="UAE Expat Wills — home">
                    <Wordmark context="header" ground="ink" />
                </Link>
                <div class="flex items-center gap-4">
                    <span class="text-caption text-steel max-[719px]:hidden">Free · no account needed</span>
                    <span class="font-mono text-micro text-steel">saved automatically</span>
                </div>
            </div>
            <ProgressStages v-if="progress" :progress="progress" />
        </header>

        <main id="main" class="flex-1 pb-32">
            <slot />
        </main>

        <!-- The action bar. Bottom-anchored, above the safe area. -->
        <div v-if="$slots.actions" class="z-actionbar safe-bottom fixed inset-x-0 bottom-0 border-t border-rule-cool bg-surface shadow-raised">
            <div class="mx-auto flex max-w-[1216px] items-center gap-3 px-8 py-3 max-[719px]:px-4">
                <slot name="actions" />
            </div>
        </div>
    </div>
</template>
