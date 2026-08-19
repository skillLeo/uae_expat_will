<script setup>
/**
 * The detailed questionnaire, one question per screen.
 *
 * Same rules as the screening assessment: back is never destructive, the
 * exclusive option clears the rest, and no question count is ever shown.
 */
import { ref, computed, watch } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import ClientLayout from '@/Layouts/ClientLayout.vue';
import ProgressStages from '@/Components/ProgressStages.vue';

const props = defineProps({
    record: { type: Object, required: true },
    question: { type: Object, required: true },
    value: { type: [String, Array, Number, Boolean], default: null },
    progress: { type: Object, required: true },
    canGoBack: { type: Boolean, default: false },
    countries: { type: Object, default: null },
});

const form = useForm({
    question_key: props.question.key,
    value: props.value ?? (props.question.multiple ? [] : ''),
});

const error = ref('');
const countryFilter = ref('');

watch(() => props.question.key, (key) => {
    form.question_key = key;
    form.value = props.value ?? (props.question.multiple ? [] : '');
    error.value = '';
    countryFilter.value = '';
});

const exclusiveKey = computed(() => props.question.options?.find((o) => o.is_exclusive)?.key ?? null);
const selected = computed(() => Array.isArray(form.value) ? form.value : (form.value ? [form.value] : []));

function pick(key) {
    error.value = '';
    if (!props.question.multiple) { form.value = key; return; }

    const current = new Set(Array.isArray(form.value) ? form.value : []);
    if (key === exclusiveKey.value) {
        form.value = current.has(key) ? [] : [key];
        return;
    }
    if (exclusiveKey.value) current.delete(exclusiveKey.value);
    current.has(key) ? current.delete(key) : current.add(key);
    form.value = [...current];
}

const filteredCountries = computed(() => {
    if (!props.countries) return [];
    const q = countryFilter.value.trim().toLowerCase();
    const entries = Object.entries(props.countries);
    return q ? entries.filter(([, n]) => n.toLowerCase().includes(q)) : entries;
});

function submit() {
    const empty = props.question.multiple ? selected.value.length === 0 : !form.value && form.value !== 0;
    if (props.question.is_required && empty) {
        error.value = 'Please answer this question to continue.';
        return;
    }
    form.post(`/client/cases/${props.record.id}/questionnaire/answer`, {
        preserveScroll: true,
        onError: (e) => { error.value = Object.values(e)[0] ?? 'Please check your answer.'; },
    });
}

const goBack = () => router.post(`/client/cases/${props.record.id}/questionnaire/back`, { question_key: props.question.key }, { preserveScroll: true });
</script>

<template>
    <ClientLayout title="Your instructions" :case-id="record.id" :back-href="`/client`">
        <ProgressStages :progress="progress" class="mb-6" />

        <div class="max-w-[62ch]">
            <h2 class="mb-3 text-h1 font-semibold leading-[1.2] text-ink">{{ question.prompt }}</h2>
            <p v-if="question.help_text" class="help mb-4">{{ question.help_text }}</p>

            <div v-if="question.privacy_note" class="card-paper mb-5 border-l-2 border-gold p-4">
                <p class="text-legal leading-[1.72] text-ink">{{ question.privacy_note }}</p>
            </div>
            <div v-if="question.security_note" class="mb-5 rounded-md border border-critical-border bg-critical-bg p-4">
                <p class="text-legal leading-[1.72] text-critical">{{ question.security_note }}</p>
            </div>

            <div v-if="countries">
                <label class="label" for="c-filter">Search the country list</label>
                <input id="c-filter" v-model="countryFilter" type="search" class="field mb-2" inputmode="search" autocomplete="country-name">
                <p class="help tabular mb-3">{{ filteredCountries.length }} matching</p>
                <div class="max-h-[360px] overflow-y-auto rounded-xs border border-rule-cool">
                    <button
                        v-for="[code, name] in filteredCountries" :key="code" type="button"
                        role="radio" :aria-checked="form.value === code"
                        class="select-row rounded-none border-x-0 border-t-0" @click="pick(code)"
                    >{{ name }}</button>
                </div>
            </div>

            <div
                v-else-if="question.options?.length" class="grid gap-1.5"
                :role="question.multiple ? 'group' : 'radiogroup'" :aria-label="question.prompt"
            >
                <button
                    v-for="o in question.options" :key="o.key" type="button"
                    :role="question.multiple ? 'checkbox' : 'radio'" :aria-checked="selected.includes(o.key)"
                    class="select-row" @click="pick(o.key)"
                >
                    <span class="block">{{ o.label }}</span>
                    <span v-if="o.description" class="help mt-1 block font-normal">{{ o.description }}</span>
                </button>
            </div>

            <textarea
                v-else-if="question.type === 'textarea'" v-model="form.value"
                class="field min-h-[140px]" :placeholder="question.placeholder"
            ></textarea>
            <input
                v-else v-model="form.value" class="field"
                :type="question.type === 'number' ? 'number' : question.type === 'date' ? 'date' : 'text'"
                :inputmode="question.inputmode ?? undefined" :placeholder="question.placeholder"
            >

            <p v-if="exclusiveKey && selected.includes(exclusiveKey)" class="help mt-3">
                Selecting this clears the other options.
            </p>

            <div class="field-slot pt-3">
                <p v-if="error" class="error" role="alert">{{ error }}</p>
            </div>
        </div>

        <template #actions>
            <button v-if="canGoBack" type="button" class="btn btn-tertiary flex-none" @click="goBack">Back</button>
            <button type="button" class="btn btn-primary flex-1" :disabled="form.processing" @click="submit">
                {{ form.processing ? 'Saving…' : 'Continue' }}
            </button>
        </template>
    </ClientLayout>
</template>
