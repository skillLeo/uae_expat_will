<script setup>
/**
 * Contact details, asked once, straight after the age question.
 *
 * This screen exists for the people who never reach the end. Collecting the
 * details on the final screen meant somebody who stopped at question nine left
 * nothing behind at all — no name, no way to ask what went wrong. Asked here,
 * an abandoned assessment is still a lead Summit can call.
 *
 * It is deliberately NOT sold as an account. There is no password, nothing to
 * confirm and nothing to log into: the promise made on every other screen is
 * that the assessment is free and needs no account, and this must not quietly
 * break it.
 */
import { useForm } from '@inertiajs/vue3';
import AssessmentLayout from '@/Layouts/AssessmentLayout.vue';
import FormField from '@/Components/FormField.vue';

const props = defineProps({
    progress: { type: Object, required: true },
    contact: { type: Object, default: () => ({}) },
});

const form = useForm({
    contact_name: props.contact.full_name ?? '',
    contact_email: props.contact.email ?? '',
    contact_phone: props.contact.phone ?? '',
});

const submit = () => form.post('/assessment/contact', { preserveScroll: true });
</script>

<template>
    <AssessmentLayout title="Your details" :progress="progress">
        <h1 class="mb-3 font-display text-h1 leading-[1.15] text-ink">
            Who should we send your result to?
        </h1>

        <p class="prose-measure mb-6 text-body leading-[1.65] text-ink-70">
            The assessment stays free and there is still no account to create. We ask now
            rather than at the end so that if you stop part way through, we can send you
            your result and pick up where you left off.
        </p>

        <form class="grid max-w-[520px] gap-4" @submit.prevent="submit">
            <FormField id="c-name" label="Full name" required :error="form.errors.contact_name">
                <input
                    id="c-name" v-model="form.contact_name" class="field"
                    autocomplete="name" enterkeyhint="next"
                    :aria-invalid="form.errors.contact_name ? 'true' : undefined"
                >
            </FormField>

            <FormField
                id="c-email" label="Email address" required
                help="Your result and your reference number are sent here."
                :error="form.errors.contact_email"
            >
                <input
                    id="c-email" v-model="form.contact_email" type="email" class="field"
                    autocomplete="email" inputmode="email" enterkeyhint="next"
                    :aria-invalid="form.errors.contact_email ? 'true' : undefined"
                >
            </FormField>

            <FormField
                id="c-phone" label="Contact number" required
                help="Used only if we need to reach you about your matter."
                :error="form.errors.contact_phone"
            >
                <input
                    id="c-phone" v-model="form.contact_phone" type="tel" class="field"
                    autocomplete="tel" inputmode="tel" enterkeyhint="done"
                    :aria-invalid="form.errors.contact_phone ? 'true' : undefined"
                >
            </FormField>

            <p class="help">
                We never ask for your instructions, your beneficiaries or any document at this
                stage. Your details are handled in accordance with our Privacy Policy.
            </p>

            <div class="pt-1">
                <button type="submit" class="btn btn-primary" :disabled="form.processing">
                    {{ form.processing ? 'Saving…' : 'Continue' }}
                </button>
            </div>
        </form>
    </AssessmentLayout>
</template>
