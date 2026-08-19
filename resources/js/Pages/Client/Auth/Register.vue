<script setup>
import { useForm } from '@inertiajs/vue3';
import { Link } from '@inertiajs/vue3';
import AuthLayout from '@/Layouts/AuthLayout.vue';
import FormField from '@/Components/FormField.vue';
import PasswordStrength from '@/Components/PasswordStrength.vue';
import StatusPill from '@/Components/StatusPill.vue';

const props = defineProps({
    reference: { type: String, default: null },
    outcome: { type: String, default: null },
    outcomeLabel: { type: String, default: null },
    allowsPayment: { type: Boolean, default: false },
    prefill: { type: Object, default: () => ({}) },
});

const form = useForm({
    full_name: props.prefill.full_name ?? '',
    email: props.prefill.email ?? '',
    phone: props.prefill.phone ?? '',
    password: '',
    password_confirmation: '',
    reference: props.reference ?? '',
    accept_terms: false,
});
</script>

<template>
    <AuthLayout title="Create your account">
        <h1 class="mb-2 font-display text-display-s text-ink">Create your account</h1>

        <!-- The reference and outcome travel from the assessment. The REASON
             a case was held never does. -->
        <div v-if="reference" class="card-paper mb-6 border border-rule-warm p-4">
            <div class="eyebrow mb-1.5">Your reference</div>
            <div class="tabular mb-2 font-mono text-body font-medium text-ink">{{ reference }}</div>
            <StatusPill v-if="outcomeLabel" :tone="allowsPayment ? 'positive' : 'held'" :label="outcomeLabel" />
            <p v-if="!allowsPayment" class="help mt-2">
                Your matter is with our legal team. Nothing is payable while it is being reviewed.
            </p>
        </div>
        <p v-else class="help mb-6">
            If you have already completed the assessment, use the link in your email so your answers
            come with you.
        </p>

        <form @submit.prevent="form.post('/client/register')">
            <FormField id="r-name" label="Full name" required :error="form.errors.full_name">
                <input id="r-name" v-model="form.full_name" class="field" autocomplete="name" required>
            </FormField>
            <FormField id="r-email" label="Email address" required :error="form.errors.email">
                <input id="r-email" v-model="form.email" type="email" class="field" inputmode="email" autocomplete="email" required>
            </FormField>
            <FormField id="r-phone" label="Telephone" :error="form.errors.phone">
                <input id="r-phone" v-model="form.phone" type="tel" class="field" inputmode="tel" autocomplete="tel">
            </FormField>

            <FormField id="r-password" label="Password" required :error="form.errors.password" help="At least 12 characters. Length beats complexity.">
                <input id="r-password" v-model="form.password" type="password" class="field" autocomplete="new-password" required>
                <PasswordStrength :password="form.password" />
            </FormField>
            <FormField id="r-confirm" label="Confirm password" required>
                <input id="r-confirm" v-model="form.password_confirmation" type="password" class="field" autocomplete="new-password" required>
            </FormField>

            <label class="mb-5 flex items-start gap-2.5">
                <input v-model="form.accept_terms" type="checkbox" class="tap mt-0.5 accent-gold" required>
                <span class="text-legal leading-[1.72] text-ink">
                    I have read the
                    <Link href="/terms-and-conditions" class="text-gold-strong underline decoration-gold underline-offset-4">Terms and Conditions</Link>
                    and the
                    <Link href="/privacy-policy" class="text-gold-strong underline decoration-gold underline-offset-4">Privacy Policy</Link>.
                </span>
            </label>
            <p v-if="form.errors.accept_terms" class="error mb-3">{{ form.errors.accept_terms }}</p>

            <button type="submit" class="btn btn-primary w-full" :disabled="form.processing">
                {{ form.processing ? 'Creating…' : 'Create my account' }}
            </button>
        </form>

        <p class="help mt-5">
            Already have an account?
            <Link href="/client/login" class="text-gold-strong underline decoration-gold underline-offset-4">Sign in</Link>
        </p>
    </AuthLayout>
</template>
