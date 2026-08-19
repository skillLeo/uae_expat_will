<script setup>
/**
 * Review before submission.
 *
 * The seven declarations are NEVER pre-ticked and all are required. The continue
 * button is gated on all seven and shows a count, so a user who missed one is
 * told which state they are in rather than left clicking a dead button.
 */
import { ref, computed } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import AssessmentLayout from '@/Layouts/AssessmentLayout.vue';

const props = defineProps({
    answers: { type: Array, default: () => [] },
    declarations: { type: Array, default: () => [] },
    progress: { type: Object, required: true },
});

// Nothing pre-ticked.
const accepted = ref([]);
const error = ref('');

const form = useForm({ declarations: [], contact: {} });

const allAccepted = computed(() => accepted.value.length === props.declarations.length);
const remaining = computed(() => props.declarations.length - accepted.value.length);

function toggle(id) {
    const i = accepted.value.indexOf(id);
    i === -1 ? accepted.value.push(id) : accepted.value.splice(i, 1);
    error.value = '';
}

function submit() {
    if (!allAccepted.value) {
        error.value = `Please confirm all ${props.declarations.length} declarations to continue.`;
        return;
    }
    form.declarations = accepted.value;
    form.post('/assessment/submit');
}

function editFrom() {
    router.post('/assessment/back', {});
}
</script>

<template>
    <AssessmentLayout :progress="progress" title="Review your answers">
        <div class="mx-auto max-w-[1216px] px-8 pt-12 max-[719px]:px-4 max-[719px]:pt-8">
            <div class="grid grid-cols-12 gap-8">
                <div class="col-span-7 max-[1080px]:col-span-full">
                    <h1 class="mb-3 text-h1 font-semibold leading-[1.2] text-ink">Check your answers</h1>
                    <p class="prose-measure mb-6 text-body leading-[1.65] text-ink-70">
                        Read these through before you submit. You can go back and change any answer —
                        nothing is sent until you confirm.
                    </p>

                    <!-- Wide: a data table. Narrow: labelled stacked cards.
                         No horizontal scroll anywhere, ever. -->
                    <div class="card overflow-hidden max-[900px]:hidden">
                        <table class="data-table">
                            <caption class="sr-only">Your answers</caption>
                            <thead>
                                <tr><th scope="col">Question</th><th scope="col">Your answer</th></tr>
                            </thead>
                            <tbody>
                                <tr v-for="row in answers" :key="row.key">
                                    <td class="text-ink-70">{{ row.prompt }}</td>
                                    <td class="font-medium text-ink">{{ row.answer }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="hidden grid gap-2 max-[900px]:grid">
                        <div v-for="row in answers" :key="row.key" class="card p-4">
                            <div class="mb-1.5 text-caption text-slate">{{ row.prompt }}</div>
                            <div class="text-body font-medium text-ink">{{ row.answer }}</div>
                        </div>
                    </div>

                    <button type="button" class="btn btn-tertiary mt-4" @click="editFrom">
                        Go back and change an answer
                    </button>
                </div>

                <div class="col-span-5 max-[1080px]:col-span-full">
                    <div class="card-paper border border-rule-warm p-6">
                        <h2 class="mb-4 text-h3 font-semibold text-ink">Before you submit</h2>

                        <div class="grid gap-3">
                            <label
                                v-for="declaration in declarations" :key="declaration.id"
                                class="flex cursor-pointer items-start gap-3 border-b border-rule-warm pb-3 last:border-0"
                            >
                                <input
                                    type="checkbox" class="tap mt-0.5 flex-none accent-gold"
                                    :checked="accepted.includes(declaration.id)"
                                    @change="toggle(declaration.id)"
                                >
                                <span class="text-legal leading-[1.72] text-ink">{{ declaration.text }}</span>
                            </label>
                        </div>

                        <p class="tabular mt-4 text-body-s" :class="allAccepted ? 'text-positive' : 'text-slate'">
                            {{ accepted.length }} of {{ declarations.length }} confirmed
                            <span v-if="!allAccepted">· {{ remaining }} remaining</span>
                        </p>

                        <div class="field-slot pt-2">
                            <p v-if="error" class="error" role="alert">{{ error }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <template #actions>
            <span class="tabular flex-1 text-body-s text-slate max-[719px]:hidden">
                {{ accepted.length }} of {{ declarations.length }} declarations confirmed
            </span>
            <button
                type="button" class="btn btn-primary max-[719px]:flex-1"
                :disabled="form.processing" @click="submit"
            >{{ form.processing ? 'Submitting…' : 'Submit my assessment' }}</button>
        </template>
    </AssessmentLayout>
</template>
