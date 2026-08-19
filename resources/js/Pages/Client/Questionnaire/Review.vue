<script setup>
import { router } from '@inertiajs/vue3';
import ClientLayout from '@/Layouts/ClientLayout.vue';
import ProgressStages from '@/Components/ProgressStages.vue';

const props = defineProps({
    record: { type: Object, required: true },
    answers: { type: Array, default: () => [] },
    progress: { type: Object, required: true },
});

const submit = () => router.post(`/client/cases/${props.record.id}/questionnaire/submit`);
const goBack = () => router.post(`/client/cases/${props.record.id}/questionnaire/back`, {});
</script>

<template>
    <ClientLayout title="Check your instructions" :case-id="record.id" back-href="/client">
        <ProgressStages :progress="progress" class="mb-6" />

        <h2 class="mb-3 text-h1 font-semibold text-ink">Check your instructions</h2>
        <p class="prose-measure mb-6 text-body leading-[1.65] text-ink-70">
            Read these through before you send them. You can go back and change any answer —
            nothing goes to the legal team until you confirm.
        </p>

        <div class="card overflow-hidden max-[900px]:hidden">
            <table class="data-table">
                <caption class="sr-only">Your instructions</caption>
                <thead><tr><th scope="col">Question</th><th scope="col">Your answer</th></tr></thead>
                <tbody>
                    <tr v-for="a in answers" :key="a.key">
                        <td class="text-ink-70">{{ a.prompt }}</td>
                        <td class="font-medium text-ink">{{ a.answer }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="hidden grid gap-2 max-[900px]:grid">
            <div v-for="a in answers" :key="a.key" class="card p-4">
                <div class="mb-1.5 text-caption text-slate">{{ a.prompt }}</div>
                <div class="text-body font-medium text-ink">{{ a.answer }}</div>
            </div>
        </div>

        <template #actions>
            <button type="button" class="btn btn-tertiary flex-none" @click="goBack">Back</button>
            <button type="button" class="btn btn-primary flex-1" @click="submit">Send to the legal team</button>
        </template>
    </ClientLayout>
</template>
