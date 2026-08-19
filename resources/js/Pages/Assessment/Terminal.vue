<script setup>
/**
 * A terminal outcome reached mid-assessment.
 *
 * The journey ends here: the remaining questions and the declarations are
 * skipped entirely, and no payment control is rendered.
 */
import { Link } from '@inertiajs/vue3';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import StatusPill from '@/Components/StatusPill.vue';

defineProps({
    outcome: { type: String, required: true },
    tone: { type: String, default: 'neutral' },
    detail: { type: String, default: null },
    screen: { type: Object, default: null },
});
</script>

<template>
    <PublicLayout title="Assessment" description="Your assessment result.">
        <section class="bg-page pb-24 pt-16 max-[719px]:py-12">
            <div class="mx-auto max-w-[1280px] px-8 max-[719px]:px-4">
                <div class="max-w-[62ch]">
                    <StatusPill
                        :tone="tone"
                        :label="outcome === 'stop_refer' ? 'A different service applies' : 'Cannot continue online'"
                        class="mb-6"
                    />
                    <h1 class="mb-5 font-display text-display-l text-ink">{{ screen?.heading }}</h1>
                    <p class="mb-4 text-body-l leading-[1.65] text-ink-70">{{ screen?.body }}</p>
                    <p v-if="detail" class="card-paper mb-8 border-l-2 border-gold p-4 text-legal leading-[1.72] text-ink">
                        {{ detail }}
                    </p>
                    <div class="flex flex-wrap items-center gap-4">
                        <Link href="/contact" class="btn btn-primary btn-lg">
                            {{ screen?.primary_action_label ?? 'Contact our team' }}
                        </Link>
                        <Link href="/" class="text-legal font-medium text-ink underline decoration-gold underline-offset-4">
                            Back to the homepage
                        </Link>
                    </div>
                    <p class="mt-6 text-legal leading-[1.72] text-ink-70">Nothing has been charged.</p>
                </div>
            </div>
        </section>
    </PublicLayout>
</template>
