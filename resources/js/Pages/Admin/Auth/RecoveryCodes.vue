<script setup>
import { Link } from '@inertiajs/vue3';
import AuthLayout from '@/Layouts/AuthLayout.vue';

defineProps({ codes: { type: Array, default: () => [] } });
</script>

<template>
    <AuthLayout title="Your recovery codes">
        <h1 class="mb-2 font-display text-display-s text-ink">Save your recovery codes</h1>
        <p class="help mb-6">
            These are shown once and never again. Each works a single time, and they are the only way
            back in if you lose your authenticator device.
        </p>

        <div class="card mb-6 p-6">
            <ul class="grid grid-cols-2 gap-2 max-[719px]:grid-cols-1">
                <li v-for="code in codes" :key="code" class="tabular font-mono text-body-s text-ink">{{ code }}</li>
            </ul>
        </div>

        <Link href="/admin" class="btn btn-primary w-full">I have saved them — continue</Link>
    </AuthLayout>
</template>
