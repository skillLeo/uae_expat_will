<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import AuthLayout from '@/Layouts/AuthLayout.vue';
import FormField from '@/Components/FormField.vue';

const form = useForm({ email: '', password: '' });
</script>

<template>
    <AuthLayout title="Sign in">
        <h1 class="mb-6 font-display text-display-s text-ink">Sign in</h1>

        <form @submit.prevent="form.post('/client/login')">
            <FormField id="l-email" label="Email address" required :error="form.errors.email">
                <input id="l-email" v-model="form.email" type="email" class="field" inputmode="email" autocomplete="email" required>
            </FormField>
            <FormField id="l-password" label="Password" required :error="form.errors.password">
                <input id="l-password" v-model="form.password" type="password" class="field" autocomplete="current-password" required>
            </FormField>

            <button type="submit" class="btn btn-primary w-full" :disabled="form.processing">
                {{ form.processing ? 'Signing in…' : 'Sign in' }}
            </button>
        </form>

        <!-- An equal option, not a fallback in small print. -->
        <div class="my-6 flex items-center gap-4">
            <div class="h-px flex-1 bg-rule-warm"></div>
            <span class="text-caption text-slate">or</span>
            <div class="h-px flex-1 bg-rule-warm"></div>
        </div>

        <Link href="/client/magic-link" class="btn btn-secondary w-full">Email me a sign-in link instead</Link>

        <div class="mt-6 flex flex-wrap justify-between gap-3">
            <Link href="/client/forgot-password" class="text-body-s text-gold-strong underline decoration-gold underline-offset-4">Forgotten your password?</Link>
            <Link href="/client/register" class="text-body-s text-gold-strong underline decoration-gold underline-offset-4">Create an account</Link>
        </div>
    </AuthLayout>
</template>
