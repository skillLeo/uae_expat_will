<script setup>
import { Link } from '@inertiajs/vue3';
import AuthLayout from '@/Layouts/AuthLayout.vue';
</script>

<template>
    <AuthLayout title="Check your email">
        <h1 class="mb-3 font-display text-display-s text-ink">Check your email</h1>
        <p class="prose-measure mb-4 text-body leading-[1.65] text-ink-70">
            If that address has a matter with us, a sign-in link is on its way. It works once and
            expires after an hour.
        </p>
        <p class="legal-measure mb-6 text-ink-70">
            We do not confirm whether an address has an account. That is deliberate — it stops this
            page being used to find out who our clients are.
        </p>
        <Link href="/client/login" class="btn btn-secondary w-full">Back to sign in</Link>
    </AuthLayout>
</template>
