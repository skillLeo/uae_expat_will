<script setup>
/**
 * The hero assessment card.
 *
 * The first two questions run inline on the homepage so the journey begins
 * before a page change. It mirrors the real assessment's rules exactly:
 * one question per screen, back always available and never destructive, no
 * question count promised anywhere, and the two terminal answers (an estate
 * matter, and being under 18) end the journey here with no payment offered.
 *
 * The answers are handed to the server on continue; this component never
 * decides an outcome on its own.
 */
import { ref, computed } from 'vue';
import { router, usePage } from '@inertiajs/vue3';

const SERVICES = [
    { id: 'new_will', label: 'Prepare a new Will for myself' },
    { id: 'two_wills', label: 'Two separate Wills for myself and my spouse or partner' },
    { id: 'difc', label: 'I specifically want a DIFC Will' },
    { id: 'review_existing', label: 'Review, amend, replace or revoke an existing Will' },
    { id: 'estate_death', label: 'Someone has died and I need help with their estate' },
];

const OUTCOMES = [
    { key: 'continue', label: 'You can continue online', detail: null },
    { key: 'review', label: 'We need to review your circumstances', detail: 'A member of the legal team contacts you before any payment.' },
    { key: 'other', label: 'The standard online pathway is not available', detail: 'We explain why, and offer a contact route where one applies.' },
];

// The fee comes from settings. It was typed in here as 2,199 and was still
// saying so after the price changed everywhere else.
const fee = computed(() => {
    const s = usePage().props.settings ?? {};
    const currency = s['commercial.currency'] ?? 'AED';
    const amount = Number(s['commercial.standard_fee'] ?? 0).toLocaleString('en-US');

    return `Straight to account, engagement terms and payment — ${currency} ${amount} plus VAT.`;
});

const step = ref('q1');           // q1 | q2 | under18 | inside
const service = ref(null);
const age = ref(null);
const error = ref('');
const submitting = ref(false);

/** Which routing outcome the current answer is heading towards. */
const liveOutcome = computed(() => {
    if (step.value === 'estate' || step.value === 'under18') return 'other';
    if (service.value === 'difc' || service.value === 'review_existing') return 'review';
    if (service.value) return 'continue';
    return null;
});

function continueFromQ1() {
    if (!service.value) {
        error.value = 'Choose one option to continue.';
        return;
    }
    error.value = '';

    // "Someone has died" used to stop here on a panel of its own, offering
    // only a link to the contact page. It never reached the request form,
    // which is why Ahmed kept reporting no contact form for it. Estate and
    // existing-Will enquiries now go to the server like every other answer,
    // and it routes them to the form with the service already selected.
    if (service.value === 'estate_death' || service.value === 'review_existing') {
        openAssessment();

        return;
    }

    step.value = 'q2';
}

function answerAge(value) {
    age.value = value;
    step.value = value === 'yes' ? 'inside' : 'under18';
}

/** Hands the two answers to the server, which starts the real assessment. */
function openAssessment() {
    submitting.value = true;

    // q2 is omitted when it has not been asked — an estate or existing-Will
    // enquiry skips the questionnaire entirely, so there is no age answer yet
    // and sending an empty one would record a blank.
    const params = { q1: service.value };

    if (age.value) {
        params.q2 = age.value;
    }

    router.get('/assessment', params, {
        onFinish: () => { submitting.value = false; },
    });
}

function back(to) {
    error.value = '';
    step.value = to;
}
</script>

<template>
    <div class="rounded-hero border-t-2 border-gold bg-paper p-6 shadow-sheet-ink max-[719px]:p-4">
        <!-- Q1 -->
        <div v-if="step === 'q1'">
            <div class="eyebrow mb-3.5">The assessment starts here</div>
            <div class="mb-1 font-mono text-micro text-slate">question one</div>
            <h2 class="mb-4 text-h3 font-semibold leading-[1.3] text-ink">What service are you looking for today?</h2>

            <div class="grid gap-1.5" role="radiogroup" aria-label="What service are you looking for today?">
                <button
                    v-for="s in SERVICES" :key="s.id" type="button" role="radio"
                    :aria-checked="service === s.id" class="select-row"
                    @click="service = s.id; error = ''"
                >
                    {{ s.label }}
                </button>
            </div>

            <div class="field-slot pt-2">
                <p v-if="error" class="error" role="alert">{{ error }}</p>
            </div>

            <button type="button" class="btn btn-primary w-full" @click="continueFromQ1">Continue</button>
        </div>

        <!-- Q2 -->
        <div v-else-if="step === 'q2'">
            <div class="eyebrow mb-3.5">About you</div>
            <div class="mb-1 font-mono text-micro text-slate">question two</div>
            <h2 class="mb-2 text-h3 font-semibold leading-[1.3] text-ink">Are you 18 years old or above?</h2>
            <p class="help mb-4">The person making the Will must be at least 18 years old.</p>

            <div class="grid gap-1.5" role="radiogroup" aria-label="Are you 18 years old or above?">
                <button type="button" role="radio" :aria-checked="age === 'yes'" class="select-row" @click="answerAge('yes')">Yes</button>
                <button type="button" role="radio" :aria-checked="age === 'no'" class="select-row" @click="answerAge('no')">No</button>
            </div>

            <button type="button" class="btn btn-tertiary mt-4 w-full" @click="back('q1')">Back</button>
        </div>

        <!-- Terminal: an estate matter. Referred, never charged. -->
        <!-- Terminal: under 18. No payment control exists on this screen at all. -->
        <div v-else-if="step === 'under18'">
            <div class="eyebrow mb-3.5">This service cannot continue</div>
            <h2 class="mb-3 text-h3 font-semibold leading-[1.3] text-ink">The person making a Will must be at least 18</h2>
            <p class="mb-5 text-body-s leading-[1.6] text-ink-70">
                You cannot continue through the online Will preparation service because the person making the
                Will must be at least 18 years old. Nothing has been charged.
            </p>
            <button type="button" class="btn btn-tertiary w-full" @click="back('q2')">Back</button>
        </div>

        <!-- Continuing. Note there is no "3 of 16" here and never will be. -->
        <div v-else>
            <div class="mb-3.5 flex items-baseline justify-between gap-3">
                <div class="eyebrow">About you</div>
                <div class="font-mono text-micro text-slate">saved automatically</div>
            </div>
            <h2 class="mb-3 text-h3 font-semibold leading-[1.3] text-ink">You are inside the assessment</h2>
            <p class="mb-3 text-body-s leading-[1.6] text-ink-70">
                Next we ask about your nationality, where you live and your family. The number of questions
                depends on your answers, so we do not promise a count.
            </p>
            <p class="help mb-5">
                You can leave and return — your answers are kept against this device until you create an account.
            </p>
            <button type="button" class="btn btn-primary mb-2 w-full" :disabled="submitting" @click="openAssessment">
                {{ submitting ? 'Opening…' : 'Continue the assessment' }}
            </button>
            <button type="button" class="btn btn-tertiary w-full" @click="back('q2')">Back</button>
        </div>
    </div>

    <!-- Trust marks -->
    <div class="mt-3 flex flex-wrap gap-x-4 gap-y-1.5">
        <span v-for="mark in ['Free', 'No account needed', 'About five minutes']" :key="mark" class="flex items-center gap-1.5">
            <svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="#D8C69A" stroke-width="1.4" class="flex-none" aria-hidden="true">
                <circle cx="8" cy="8" r="6.2" />
                <polyline points="5.2,8.3 7.2,10.2 10.9,5.9" stroke-linecap="round" stroke-linejoin="round" />
            </svg>
            <span class="text-caption text-steel">{{ mark }}</span>
        </span>
    </div>

    <!-- The routing rail: shows where the current answer is heading, honestly. -->
    <div class="mt-5">
        <div class="eyebrow mb-2.5">Where your answer goes</div>
        <div class="ml-[11px] h-3 w-px bg-gold-soft"></div>
        <div class="ml-[11px] grid gap-1.5 border-l border-gold-soft">
            <div v-for="o in OUTCOMES" :key="o.key" class="grid grid-cols-[20px_minmax(0,1fr)] items-center">
                <div
                    class="h-px origin-left bg-gold transition-transform duration-300"
                    :style="{ transform: liveOutcome === o.key ? 'scaleX(1)' : 'scaleX(0)' }"
                ></div>
                <div class="rounded-sm border px-3.5 py-2.5 transition-colors duration-300" :class="liveOutcome === o.key ? 'border-gold' : 'border-ink-line'">
                    <div class="text-body-s font-medium leading-[1.4]" :class="liveOutcome === o.key ? 'text-paper' : 'text-steel'">{{ o.label }}</div>
                    <div v-if="liveOutcome === o.key" class="mt-1 text-caption leading-[1.5] text-steel">{{ o.detail ?? fee }}</div>
                </div>
            </div>
        </div>
    </div>
</template>
