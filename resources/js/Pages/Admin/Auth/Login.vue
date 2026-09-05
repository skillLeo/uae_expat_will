<script setup>
/**
 * Two-step sign-in: email, then password.
 *
 * Neither step reveals whether the account exists — step one always advances,
 * and step two always returns the same message.
 */
import { ref, watch, nextTick } from 'vue';
import { useForm } from '@inertiajs/vue3';
import AuthLayout from '@/Layouts/AuthLayout.vue';

const props = defineProps({
    step: { type: String, default: 'email' },
    email: { type: String, default: null },
});

const identify = useForm({ email: props.email ?? '' });
const login = useForm({ password: '', session_length: 'standard' });

const passwordField = ref(null);

/**
 * Sign in, with the email taken from the server on every submit.
 *
 * It used to live in the form's own state, seeded from props when the
 * component was set up. Inertia reuses this component between the two steps —
 * it swaps the props and never runs setup() again — so on step two the email
 * was still the empty string captured on step one. Every sign-in therefore
 * posted a blank email, failed validation on a field this form does not
 * display, and looked to the person signing in like the button doing nothing.
 * They clicked again, and again, and it only ever worked after a full reload.
 *
 * Reading it from props at submit time cannot drift, whatever Inertia does
 * with the component.
 */
function submit() {
    login
        .transform((data) => ({ ...data, email: props.email }))
        .post('/admin/login', { preserveScroll: true });
}

// autofocus only fires on mount, and this component is not remounted between
// the two steps, so the password field has to be focused explicitly.
watch(
    () => props.step,
    (step) => {
        if (step === 'password') {
            nextTick(() => passwordField.value?.focus());
        }
    },
    { immediate: true },
);

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
        <form v-else @submit.prevent="submit">
            <p class="help mb-6">Signing in as <span class="font-medium text-ink">{{ email }}</span></p>

            <label class="label" for="password">Password</label>
            <input
                id="password" ref="passwordField" v-model="login.password" type="password" class="field"
                autocomplete="current-password" required
                :aria-invalid="!!login.errors.password || !!login.errors.email"
            >
            <div class="field-slot pt-1.5">
                <!--
                    Every error is shown, not only the password one. A failure
                    on any other field used to render nothing at all, so the
                    button appeared to do nothing and people clicked it
                    repeatedly.
                -->
                <p v-for="(message, field) in login.errors" :key="field" class="error" role="alert">
                    {{ message }}
                </p>
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
