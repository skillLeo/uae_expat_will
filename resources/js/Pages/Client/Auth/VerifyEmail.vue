<script setup>
import { router, usePage } from '@inertiajs/vue3';
import AuthLayout from '@/Layouts/AuthLayout.vue';

const resend = () => router.post('/client/verify-email/resend');
</script>

<template>
    <AuthLayout title="Confirm your email address">
        <h1 class="mb-3 font-display text-display-s text-ink">Confirm your email address</h1>
        <p class="prose-measure mb-4 text-body leading-[1.65] text-ink-70">
            We have sent a link to the address you gave us. Opening it confirms we can reach you —
            which matters, because everything about your matter is exchanged inside your account
            rather than by message.
        </p>

        <p v-if="$page.props.flash?.success" class="mb-4 rounded-md border border-positive-border bg-positive-bg p-3 text-body-s text-positive">
            {{ $page.props.flash.success }}
        </p>

        <button type="button" class="btn btn-secondary w-full" @click="resend">Send it again</button>
    </AuthLayout>
</template>
