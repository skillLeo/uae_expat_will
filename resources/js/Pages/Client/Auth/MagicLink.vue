<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import AuthLayout from '@/Layouts/AuthLayout.vue';
import FormField from '@/Components/FormField.vue';

const form = useForm({ email: '' });
</script>

<template>
    <AuthLayout title="Email me a sign-in link">
        <h1 class="mb-2 font-display text-display-s text-ink">Email me a sign-in link</h1>
        <p class="help mb-6">
            No password needed. We send a link that works once and expires after an hour.
        </p>

        <form @submit.prevent="form.post('/client/magic-link')">
            <FormField id="m-email" label="Email address" required :error="form.errors.email">
                <input id="m-email" v-model="form.email" type="email" class="field" inputmode="email" autocomplete="email" required autofocus>
            </FormField>
            <button type="submit" class="btn btn-primary w-full" :disabled="form.processing">
                {{ form.processing ? 'Sending…' : 'Send my link' }}
            </button>
        </form>

        <Link href="/client/login" class="mt-6 block text-body-s text-gold-strong underline decoration-gold underline-offset-4">
            Use a password instead
        </Link>
    </AuthLayout>
</template>
