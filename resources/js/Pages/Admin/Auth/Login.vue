<script setup>
/**
 * Two-step sign-in: email, then password.
 *
 * Neither step reveals whether the account exists — step one always advances,
 * and step two always returns the same message.
 */
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import AuthLayout from '@/Layouts/AuthLayout.vue';

const props = defineProps({
    step: { type: String, default: 'email' },
    email: { type: String, default: null },
});

const identify = useForm({ email: props.email ?? '' });
const login = useForm({ email: props.email ?? '', password: '', session_length: 'standard' });

const LENGTHS = [
    { value: 'short', label: '2 hours', hint: 'A shared or public computer' },
    { value: 'standard', label: '8 hours', hint: 'A normal working day' },
    { value: 'extended', label: '7 days', hint: 'Your own device only' },
];
</script>

<template>
    <AuthLayout title="Sign in">
        <h1 class="mb-2 font-display text-display-s text-ink">Sign in</h1>

        <!-- Step one -->
        <form v-if="step === 'email'" @submit.prevent="identify.post('/admin/login/identify')">
            <p class="help mb-6">Summit staff only.</p>

            <label class="label" for="email">Email address</label>
            <input
                id="email" v-model="identify.email" type="email" class="field"
                autocomplete="username" inputmode="email" autofocus required
                :aria-invalid="!!identify.errors.email"
            >
            <div class="field-slot pt-1.5">
                <p v-if="identify.errors.email" class="error">{{ identify.errors.email }}</p>
            </div>

            <button type="submit" class="btn btn-primary w-full" :disabled="identify.processing">
                {{ identify.processing ? 'Checking…' : 'Continue' }}
            </button>
        </form>

        <!-- Step two -->
        <form v-else @submit.prevent="login.post('/admin/login')">
            <p class="help mb-6">Signing in as <span class="font-medium text-ink">{{ email }}</span></p>

            <input v-model="login.email" type="hidden">

            <label class="label" for="password">Password</label>
            <input
                id="password" v-model="login.password" type="password" class="field"
                autocomplete="current-password" autofocus required
                :aria-invalid="!!login.errors.password"
            >
            <div class="field-slot pt-1.5">
                <p v-if="login.errors.password" class="error" role="alert">{{ login.errors.password }}</p>
            </div>

            <!-- A stated session length rather than an opaque "remember me". -->
            <fieldset class="mb-6">
                <legend class="label">Keep me signed in for</legend>
                <div class="grid gap-1.5">
                    <label
                        v-for="option in LENGTHS" :key="option.value"
                        class="select-row flex cursor-pointer items-center justify-between gap-3"
                        :aria-checked="login.session_length === option.value" role="radio"
                    >
                        <span>
                            <span class="block text-body-s font-medium">{{ option.label }}</span>
                            <span class="help">{{ option.hint }}</span>
                        </span>
                        <input v-model="login.session_length" type="radio" :value="option.value" class="sr-only">
                    </label>
                </div>
            </fieldset>

            <button type="submit" class="btn btn-primary w-full" :disabled="login.processing">
                {{ login.processing ? 'Signing in…' : 'Sign in' }}
            </button>
        </form>
    </AuthLayout>
</template>
