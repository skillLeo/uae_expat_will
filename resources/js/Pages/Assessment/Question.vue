<script setup>
/**
 * One question per screen.
 *
 * The exclusive option ("None of these") clears every other selection here for
 * the sake of the interaction — and is revalidated server-side, because the
 * client is not a line of defence.
 */
import { ref, computed, watch } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import AssessmentLayout from '@/Layouts/AssessmentLayout.vue';

const props = defineProps({
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

const exclusiveKey = computed(
    () => props.question.options?.find((o) => o.is_exclusive)?.key ?? null,
);

const selected = computed(() =>
    Array.isArray(form.value) ? form.value : (form.value ? [form.value] : []),
);

function pick(optionKey) {
    error.value = '';

    if (!props.question.multiple) {
        form.value = optionKey;
        return;
    }

    const current = new Set(Array.isArray(form.value) ? form.value : []);

    if (optionKey === exclusiveKey.value) {
        // The exclusive option clears everything else.
        form.value = current.has(optionKey) ? [] : [optionKey];
        return;
    }

    // Choosing anything else clears the exclusive option.
    if (exclusiveKey.value) current.delete(exclusiveKey.value);
    current.has(optionKey) ? current.delete(optionKey) : current.add(optionKey);
    form.value = [...current];
}

const exclusiveActive = computed(
    () => exclusiveKey.value !== null && selected.value.includes(exclusiveKey.value),
);

const filteredCountries = computed(() => {
    if (!props.countries) return [];
    const entries = Object.entries(props.countries);
    const q = countryFilter.value.trim().toLowerCase();
    if (!q) return entries;
    return entries.filter(([, name]) => name.toLowerCase().includes(q));
});

function submit() {
    const empty = props.question.multiple
        ? selected.value.length === 0
        : !form.value && form.value !== 0;

    if (props.question.is_required && empty) {
        error.value = 'Please answer this question to continue.';
        return;
    }

    form.post('/assessment/answer', {
        preserveScroll: true,
        onError: (errors) => { error.value = Object.values(errors)[0] ?? 'Please check your answer.'; },
    });
}

function goBack() {
    router.post('/assessment/back', { question_key: props.question.key }, { preserveScroll: true });
}
</script>

<template>
    <AssessmentLayout :progress="progress" title="Assessment">
        <div class="mx-auto max-w-[1216px] px-8 pt-12 max-[719px]:px-4 max-[719px]:pt-8">
            <div class="max-w-[62ch]">
                <h1 class="mb-3 text-h1 font-semibold leading-[1.2] text-ink">{{ question.prompt }}</h1>

                <p v-if="question.help_text" class="help mb-4">{{ question.help_text }}</p>

                <!-- Why we ask, stated on the same screen as the question. -->
                <div v-if="question.privacy_note" class="card-paper mb-5 border-l-2 border-gold p-4">
                    <p class="text-legal leading-[1.72] text-ink">{{ question.privacy_note }}</p>
                </div>

                <div v-if="question.security_note" class="mb-5 rounded-md border border-critical-border bg-critical-bg p-4">
                    <p class="text-legal leading-[1.72] text-critical">{{ question.security_note }}</p>
                </div>

                <!-- Country select: type-ahead with a match count in tabular figures. -->
                <div v-if="countries">
                    <label class="label" for="country-filter">Search the country list</label>
                    <input
                        id="country-filter" v-model="countryFilter" type="search" class="field mb-2"
                        inputmode="search" autocomplete="country-name" placeholder="Start typing a country"
                    >
                    <p class="help tabular mb-3">{{ filteredCountries.length }} matching</p>
                    <div class="scroll-x max-h-[360px] overflow-y-auto rounded-xs border border-rule-cool">
                        <button
                            v-for="[code, name] in filteredCountries" :key="code"
                            type="button" role="radio" :aria-checked="form.value === code"
                            class="select-row rounded-none border-x-0 border-t-0"
                            @click="pick(code)"
                        >{{ name }}</button>
                    </div>
                    <p v-if="filteredCountries.length === 0" class="help mt-3">
                        No country matches “{{ countryFilter }}”.
                    </p>
                </div>

                <!-- Option rows: the row IS the control. -->
                <div
                    v-else-if="question.options?.length"
                    class="grid gap-1.5"
                    :role="question.multiple ? 'group' : 'radiogroup'"
                    :aria-label="question.prompt"
                >
                    <button
                        v-for="option in question.options" :key="option.key"
                        type="button"
                        :role="question.multiple ? 'checkbox' : 'radio'"
                        :aria-checked="selected.includes(option.key)"
                        class="select-row"
                        @click="pick(option.key)"
                    >
                        <span class="block">{{ option.label }}</span>
                        <span v-if="option.description" class="help mt-1 block font-normal">{{ option.description }}</span>
                    </button>
                </div>

                <!-- Free text -->
                <textarea
                    v-else-if="question.type === 'textarea'"
                    v-model="form.value" class="field min-h-[140px]"
                    :placeholder="question.placeholder"
                ></textarea>
                <input
                    v-else v-model="form.value" class="field"
                    :type="question.type === 'number' ? 'number' : question.type === 'date' ? 'date' : 'text'"
                    :inputmode="question.inputmode ?? undefined"
                    :placeholder="question.placeholder"
                >

                <!-- The exclusive-option note, stated rather than left to inference. -->
                <p v-if="exclusiveActive" class="help mt-3">
                    Selecting this clears the other options. Choose any other answer to undo it.
                </p>

                <div class="field-slot pt-3">
                    <p v-if="error" class="error" role="alert">{{ error }}</p>
                </div>
            </div>
        </div>

        <template #actions>
            <button
                v-if="canGoBack" type="button" class="btn btn-tertiary flex-none"
                @click="goBack"
            >Back</button>
            <button
                type="button" class="btn btn-primary flex-1" :disabled="form.processing"
                @click="submit"
            >{{ form.processing ? 'Saving…' : 'Continue' }}</button>
        </template>
    </AssessmentLayout>
</template>
