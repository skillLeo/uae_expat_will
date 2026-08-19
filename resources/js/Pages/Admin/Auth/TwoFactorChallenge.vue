<script setup>
import { useForm } from '@inertiajs/vue3';
import AuthLayout from '@/Layouts/AuthLayout.vue';

const form = useForm({ code: '' });
</script>

<template>
    <AuthLayout title="Two-factor authentication">
        <h1 class="mb-2 font-display text-display-s text-ink">Enter your code</h1>
        <p class="help mb-6">
            Open your authenticator app and enter the six-digit code. You can also use one of your
            recovery codes.
        </p>

        <form @submit.prevent="form.post('/admin/two-factor/verify')">
            <label class="label" for="code">Code</label>
            <input
                id="code" v-model="form.code" type="text" class="field tabular text-center font-mono"
                style="font-size: 22px; letter-spacing: 0.3em;"
                inputmode="numeric" autocomplete="one-time-code" maxlength="11" autofocus required
                :aria-invalid="!!form.errors.code"
            >
            <div class="field-slot pt-1.5">
                <p v-if="form.errors.code" class="error" role="alert">{{ form.errors.code }}</p>
            </div>
            <button type="submit" class="btn btn-primary w-full" :disabled="form.processing">
                {{ form.processing ? 'Verifying…' : 'Verify' }}
            </button>
        </form>
    </AuthLayout>
</template>
