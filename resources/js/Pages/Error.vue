<script setup>
/**
 * Error pages.
 *
 * Each one says what happened, what it means for the reader's matter, gives one
 * primary action and a real contact route. No apology, no "oops", no vagueness —
 * somebody who hits an error in the middle of a legal process wants to know
 * whether their matter is affected, and that is the first thing each of these
 * answers.
 */
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import StatusPill from '@/Components/StatusPill.vue';

const props = defineProps({
    status: { type: [Number, String], default: 500 },
    reference: { type: String, default: null },
});

const COPY = {
    404: {
        code: '404 · page not found',
        title: 'That page is not here',
        body: 'The address you followed does not match a page on this site. A link may have been mistyped, or a page may have been renamed.',
        detail: 'Nothing has gone wrong with your matter. If you were partway through the assessment, your answers are still held against this device and you can pick up where you left off.',
        primary: { label: 'Go to the homepage', href: '/' },
        secondary: { label: 'Start the assessment', href: '/assessment' },
        asideTitle: 'Most people arriving here wanted',
        aside: 'The assessment, the pricing page, or the FAQs. All three are linked from the footer of every page.',
        tone: 'neutral',
    },
    500: {
        code: '500 · server error',
        title: 'Something on our side failed',
        body: 'The page could not be built because of a fault on our servers, not anything you did. The fault has been logged and our team has been notified automatically.',
        detail: 'If you were partway through a form, your last saved step is intact. Nothing has been submitted twice and no payment has been taken as a result of this.',
        primary: { label: 'Try that page again', href: '/' },
        secondary: { label: 'Contact our team', href: '/contact' },
        asideTitle: 'If it happens twice',
        aside: 'Email us with the reference below and roughly what you were doing. It lets us find the exact fault in the log rather than guessing.',
        tone: 'critical',
    },
    503: {
        code: '503 · temporarily down',
        title: 'We are briefly offline',
        body: 'The site is down for a short maintenance window. This is planned and it is not a fault.',
        detail: 'Nothing about your matter is affected. Anything you had saved is exactly where you left it.',
        primary: { label: 'Try again', href: '/' },
        secondary: { label: 'Contact our team', href: '/contact' },
        asideTitle: 'How long',
        aside: 'These windows are kept short. If it is still down in an hour, please do email us.',
        tone: 'attention',
    },
    403: {
        code: '403 · not available to you',
        title: 'You cannot open that',
        body: 'That page belongs to an account or a matter that is not yours, or it needs a permission this account does not hold.',
        detail: 'If you believe you should have access, contact the team rather than trying another route in.',
        primary: { label: 'Go to the homepage', href: '/' },
        secondary: { label: 'Contact our team', href: '/contact' },
        asideTitle: 'Signed in as someone else?',
        aside: 'If you have more than one account, sign out and back in with the one the matter belongs to.',
        tone: 'held',
    },
    419: {
        code: '419 · session expired',
        title: 'Your session timed out',
        body: 'You were away long enough that we closed the session, which is what stops an unattended screen being used by somebody else.',
        detail: 'Nothing was lost. Sign in again and the page you wanted will be waiting.',
        primary: { label: 'Start again', href: '/' },
        secondary: { label: 'Contact our team', href: '/contact' },
        asideTitle: 'Why we do this',
        aside: 'Sessions on a legal matter are deliberately short. It is a small inconvenience against a real risk.',
        tone: 'attention',
    },
};

const copy = computed(() => COPY[String(props.status)] ?? COPY[500]);
</script>

<template>
    <PublicLayout :title="copy.title" :description="copy.body">
        <section class="bg-page pb-24 pt-16 max-[719px]:py-12">
            <div class="mx-auto max-w-[1280px] px-8 max-[719px]:px-4">
                <div class="grid grid-cols-12 gap-8">
                    <div class="col-span-7 max-[1080px]:col-span-full">
                        <StatusPill :tone="copy.tone" :label="copy.code" class="mb-6" />
                        <h1 class="mb-5 max-w-[22ch] font-display text-display-l text-ink">{{ copy.title }}</h1>
                        <p class="prose-measure mb-4 text-body-l leading-[1.65] text-ink-70">{{ copy.body }}</p>
                        <p class="legal-measure mb-8 text-ink">{{ copy.detail }}</p>

                        <div class="flex flex-wrap items-center gap-4">
                            <Link :href="copy.primary.href" class="btn btn-primary btn-lg">{{ copy.primary.label }}</Link>
                            <Link :href="copy.secondary.href" class="text-legal font-medium text-ink underline decoration-gold underline-offset-4">
                                {{ copy.secondary.label }}
                            </Link>
                        </div>
                    </div>

                    <aside class="card col-span-4 col-start-9 self-start p-6 max-[1080px]:col-span-full max-[1080px]:col-start-1">
                        <div class="eyebrow mb-2">{{ copy.asideTitle }}</div>
                        <p class="text-body-s leading-[1.6] text-ink-70">{{ copy.aside }}</p>
                        <div v-if="reference" class="mt-4 border-t border-rule-cool pt-4">
                            <div class="eyebrow mb-1">Reference</div>
                            <code class="tabular font-mono text-body-s text-ink">{{ reference }}</code>
                        </div>
                    </aside>
                </div>
            </div>
        </section>
    </PublicLayout>
</template>
