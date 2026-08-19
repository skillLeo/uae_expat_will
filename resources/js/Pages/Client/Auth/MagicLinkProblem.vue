<script setup>
/**
 * The four distinct magic-link failure screens.
 *
 * "This link does not work" tells somebody nothing. Each of these says what
 * happened, what it means for their matter, and exactly what to do next.
 */
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import AuthLayout from '@/Layouts/AuthLayout.vue';
import StatusPill from '@/Components/StatusPill.vue';

const props = defineProps({
    reason: { type: String, default: 'invalid' },
    reference: { type: String, default: null },
});

const COPY = {
    expired: {
        pill: 'Link expired',
        title: 'That link has expired',
        body: 'Links to your questionnaire and your documents are time-limited, which is what stops a forwarded email from opening your matter. This one has passed its window.',
        detail: 'Your matter is unaffected and nothing has been lost. Sign in and the same page will be waiting, or ask us for a new link.',
        primary: { label: 'Send me a new link', href: '/client/magic-link' },
    },
    used: {
        pill: 'Already used',
        title: 'That link has already been used',
        body: 'Each link works exactly once. If you have already opened it, everything you did is saved and waiting for you.',
        detail: 'If you did not use it yourself, contact us — a used link you did not open is worth telling us about.',
        primary: { label: 'Send me a new link', href: '/client/magic-link' },
    },
    revoked: {
        pill: 'Link revoked',
        title: 'That link was withdrawn',
        body: 'Someone at Summit withdrew this link. That usually means a newer one was issued, or the details changed.',
        detail: 'Nothing has happened to your matter. Ask for a fresh link, or contact the team if you were not expecting this.',
        primary: { label: 'Send me a new link', href: '/client/magic-link' },
    },
    no_account: {
        pill: 'No account yet',
        title: 'There is no account on this matter yet',
        body: 'The link is valid, but nobody has created an account against this reference.',
        detail: 'Create one and your matter will be waiting inside it.',
        primary: { label: 'Create my account', href: '/client/register' },
    },
    invalid: {
        pill: 'Link not recognised',
        title: 'We do not recognise that link',
        body: 'The address may have been mistyped, or the link may have been broken across two lines by an email client.',
        detail: 'Try copying the whole link again, or ask us for a new one.',
        primary: { label: 'Send me a new link', href: '/client/magic-link' },
    },
};

const copy = computed(() => COPY[props.reason] ?? COPY.invalid);
</script>

<template>
    <AuthLayout :title="copy.title">
        <StatusPill tone="attention" :label="copy.pill" class="mb-5" />
        <h1 class="mb-4 font-display text-display-s text-ink">{{ copy.title }}</h1>
        <p class="prose-measure mb-3 text-body leading-[1.65] text-ink-70">{{ copy.body }}</p>
        <p class="legal-measure mb-6 text-ink">{{ copy.detail }}</p>

        <div v-if="reference" class="card-paper mb-6 border border-rule-warm p-4">
            <div class="eyebrow mb-1">Your reference</div>
            <div class="tabular font-mono text-body text-ink">{{ reference }}</div>
        </div>

        <Link :href="copy.primary.href" class="btn btn-primary mb-2 w-full">{{ copy.primary.label }}</Link>
        <Link href="/client/login" class="btn btn-secondary w-full">Sign in with a password</Link>
    </AuthLayout>
</template>
