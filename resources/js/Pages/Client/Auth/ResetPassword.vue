<script setup>
import { useForm } from '@inertiajs/vue3';
import AuthLayout from '@/Layouts/AuthLayout.vue';
import FormField from '@/Components/FormField.vue';
import PasswordStrength from '@/Components/PasswordStrength.vue';

const props = defineProps({ token: { type: String, required: true }, email: { type: String, default: '' } });

const form = useForm({
    token: props.token,
    email: props.email,
    password: '',
    password_confirmation: '',
});
</script>

<template>
    <AuthLayout title="Choose a new password">
        <h1 class="mb-6 font-display text-display-s text-ink">Choose a new password</h1>

        <form @submit.prevent="form.post('/client/reset-password')">
            <FormField id="rp-email" label="Email address" required :error="form.errors.email">
                <input id="rp-email" v-model="form.email" type="email" class="field" inputmode="email" autocomplete="email" required>
            </FormField>
            <FormField id="rp-password" label="New password" required :error="form.errors.password" help="At least 12 characters.">
                <input id="rp-password" v-model="form.password" type="password" class="field" autocomplete="new-password" required>
                <PasswordStrength :password="form.password" />
            </FormField>
            <FormField id="rp-confirm" label="Confirm new password" required>
                <input id="rp-confirm" v-model="form.password_confirmation" type="password" class="field" autocomplete="new-password" required>
            </FormField>
            <button type="submit" class="btn btn-primary w-full" :disabled="form.processing">
                {{ form.processing ? 'Saving…' : 'Set new password' }}
            </button>
        </form>
    </AuthLayout>
</template>
