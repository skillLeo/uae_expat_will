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
    isMirror: { type: Boolean, default: false },
    partner: { type: Object, default: () => ({}) },
    countries: { type: Array, default: null },
    partnerNotice: { type: String, default: null },
});

const form = useForm({
    contact_name: props.contact.full_name ?? '',
    contact_email: props.contact.email ?? '',
    contact_phone: props.contact.phone ?? '',
    // Mirror Wills only. Sent regardless and ignored by the server otherwise,
    // which keeps the request shape stable.
    partner_name: props.partner?.name ?? '',
    partner_nationality: props.partner?.nationality ?? '',
    partner_phone: props.partner?.phone ?? '',
    partner_email: props.partner?.email ?? '',
    partner_email_confirmation: props.partner?.email ?? '',
});

const submit = () => form.post('/assessment/contact', { preserveScroll: true });
</script>

<template>
    <AssessmentLayout title="Your details" :progress="progress">
        <h1 class="mb-3 font-display text-h1 leading-[1.15] text-ink">
            {{ isMirror ? 'Who are the two Wills for?' : 'Who should we send your result to?' }}
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

            <!--
                Mirror Wills: the partner's details, on the same screen.

                Required rather than optional, because the pair is the service:
                the first person pays for both, and their partner is invited
                straight away rather than chased for later.
            -->
            <template v-if="isMirror">
                <div class="mt-4 border-t border-rule-cool pt-6">
                    <h2 class="mb-1 text-h2 font-semibold text-ink">Your spouse or partner</h2>
                    <p class="prose-measure mb-4 text-body leading-[1.65] text-ink-70">
                        Their Will is a separate document that they approve themselves, so we need
                        their details too. We contact them directly once you continue.
                    </p>

                    <div class="grid gap-4">
                        <FormField id="p-name" label="Partner's full name" required :error="form.errors.partner_name">
                            <input
                                id="p-name" v-model="form.partner_name" class="field"
                                autocomplete="off" enterkeyhint="next"
                                :aria-invalid="form.errors.partner_name ? 'true' : undefined"
                            >
                        </FormField>

                        <FormField
                            id="p-nat" label="Partner's nationality" required
                            :help="partnerNotice"
                            :error="form.errors.partner_nationality"
                        >
                            <select
                                id="p-nat" v-model="form.partner_nationality" class="field"
                                :aria-invalid="form.errors.partner_nationality ? 'true' : undefined"
                            >
                                <option value="" disabled>Select a nationality</option>
                                <option v-for="c in countries" :key="c.code" :value="c.code">{{ c.name }}</option>
                            </select>
                        </FormField>

                        <FormField id="p-phone" label="Partner's contact number" required :error="form.errors.partner_phone">
                            <input
                                id="p-phone" v-model="form.partner_phone" type="tel" class="field"
                                autocomplete="off" inputmode="tel" enterkeyhint="next"
                                :aria-invalid="form.errors.partner_phone ? 'true' : undefined"
                            >
                        </FormField>

                        <FormField
                            id="p-email" label="Partner's email address" required
                            help="Their invitation and their own questionnaire link are sent here."
                            :error="form.errors.partner_email"
                        >
                            <input
                                id="p-email" v-model="form.partner_email" type="email" class="field"
                                autocomplete="off" inputmode="email" enterkeyhint="next"
                                :aria-invalid="form.errors.partner_email ? 'true' : undefined"
                            >
                        </FormField>

                        <!--
                            Asked twice on purpose. A typo in your own address
                            corrects itself, because nothing arrives. A typo in
                            someone else's does not: the invitation goes
                            silently nowhere and nobody finds out for days.
                        -->
                        <FormField
                            id="p-email2" label="Confirm partner's email address" required
                            :error="form.errors.partner_email_confirmation"
                        >
                            <input
                                id="p-email2" v-model="form.partner_email_confirmation" type="email" class="field"
                                autocomplete="off" inputmode="email" enterkeyhint="done"
                                :aria-invalid="form.errors.partner_email_confirmation ? 'true' : undefined"
                            >
                        </FormField>

                        <p
                            v-if="form.partner_email && form.partner_email_confirmation
                                && form.partner_email === form.partner_email_confirmation"
                            class="help"
                        >
                            We will contact your partner at <strong class="text-ink">{{ form.partner_email }}</strong>.
                        </p>
                    </div>
                </div>
            </template>

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
