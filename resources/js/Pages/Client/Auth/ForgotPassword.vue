<script setup>
import { Link, useForm, usePage } from '@inertiajs/vue3';
import AuthLayout from '@/Layouts/AuthLayout.vue';
import FormField from '@/Components/FormField.vue';

const form = useForm({ email: '' });
</script>

<template>
    <AuthLayout title="Reset your password">
        <h1 class="mb-2 font-display text-display-s text-ink">Reset your password</h1>
        <p class="help mb-6">We will email you a link to set a new one.</p>

        <p v-if="$page.props.flash?.success" class="mb-4 rounded-md border border-positive-border bg-positive-bg p-3 text-body-s text-positive">
            {{ $page.props.flash.success }}
        </p>

        <form @submit.prevent="form.post('/client/forgot-password')">
            <FormField id="f-email" label="Email address" required :error="form.errors.email">
                <input id="f-email" v-model="form.email" type="email" class="field" inputmode="email" autocomplete="email" required autofocus>
            </FormField>
            <button type="submit" class="btn btn-primary w-full" :disabled="form.processing">
                {{ form.processing ? 'Sending…' : 'Send reset link' }}
            </button>
        </form>

        <Link href="/client/login" class="mt-6 block text-body-s text-gold-strong underline decoration-gold underline-offset-4">Back to sign in</Link>
    </AuthLayout>
</template>
