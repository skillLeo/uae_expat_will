<script setup>
/**
 * Draft review, amendments and approval.
 *
 * The caveat below the approve button is not decoration: approving the wording
 * is the client's act, registration is the authority's, and the two must never
 * be allowed to blur.
 */
import { ref, reactive } from 'vue';
import { router } from '@inertiajs/vue3';
import ClientLayout from '@/Layouts/ClientLayout.vue';
import StatusPill from '@/Components/StatusPill.vue';
import Sheet from '@/Components/Sheet.vue';
import FormField from '@/Components/FormField.vue';

const props = defineProps({
    record: { type: Object, required: true },
    drafts: { type: Array, default: () => [] },
    allowance: { type: Number, default: 2 },
});

const amendSheet = ref(null);
const approveSheet = ref(null);
const amendBody = ref('');
const confirmed = ref(false);

const requestAmendment = () => router.post(`/client/drafts/${amendSheet.value.id}/amendments`, { body: amendBody.value }, {
    preserveScroll: true,
    onSuccess: () => { amendSheet.value = null; amendBody.value = ''; },
});

const approve = () => router.post(`/client/drafts/${approveSheet.value.id}/approve`, { confirm: true }, {
    preserveScroll: true,
    onSuccess: () => { approveSheet.value = null; confirmed.value = false; },
});

const tone = (status) => ({
    draft: 'neutral', sent: 'progress', amendments_requested: 'attention', approved: 'positive',
}[status] ?? 'neutral');
</script>

<template>
    <ClientLayout title="Your draft" :case-id="record.id" back-href="/client">
        <div v-if="!drafts.length" class="card p-8 text-center">
            <h2 class="mb-2 text-h3 font-semibold text-ink">No draft yet</h2>
            <p class="help">
                Your draft appears here once the legal team has completed its review. We will email you.
            </p>
        </div>

        <article v-for="d in drafts" :key="d.id" class="card mb-4 p-6 max-[719px]:p-4">
            <div class="mb-4 flex flex-wrap items-start justify-between gap-3">
                <div>
                    <div class="eyebrow mb-1">Draft {{ d.version_number }}</div>
                    <p v-if="d.sent_at" class="tabular font-mono text-caption text-slate">
                        Sent {{ new Date(d.sent_at).toLocaleDateString('en-GB') }}
                    </p>
                </div>
                <StatusPill :tone="tone(d.status)" :label="d.status.replace(/_/g, ' ')" />
            </div>

            <div class="mb-5 flex flex-wrap gap-2">
                <a v-if="d.url" :href="d.url" class="btn btn-primary" target="_blank" rel="noopener">Read the draft</a>
                <button v-if="!d.approved" type="button" class="btn btn-secondary" @click="amendSheet = d">Request a change</button>
                <button v-if="!d.approved && d.url" type="button" class="btn btn-secondary" @click="approveSheet = d">Approve the wording</button>
            </div>

            <p class="tabular help mb-4">
                {{ d.amendments_used }} of {{ allowance }} included amendment rounds used.
            </p>

            <div v-if="d.amendments.length">
                <div class="eyebrow mb-2">Your requests</div>
                <div class="grid gap-2">
                    <div v-for="(a, i) in d.amendments" :key="i" class="border-b border-rule-cool pb-2 last:border-0">
                        <p class="mb-1 whitespace-pre-line text-body-s text-ink">{{ a.body }}</p>
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="tabular font-mono text-caption text-slate">{{ new Date(a.at).toLocaleDateString('en-GB') }}</span>
                            <span v-if="!a.within_allowance" class="pill pill-attention">beyond the included allowance</span>
                        </div>
                    </div>
                </div>
            </div>

            <div v-if="d.approved" class="mt-4 rounded-md border border-positive-border bg-positive-bg p-4">
                <p class="text-body-s font-medium text-positive">
                    You approved this wording on {{ new Date(d.approved_at).toLocaleString('en-GB') }}.
                </p>
                <p class="mt-1 text-legal leading-[1.72] text-ink">
                    Approving the wording does not register the Will. Registration is completed by the
                    competent authority under its own current requirements.
                </p>
            </div>
        </article>

        <Sheet :open="amendSheet !== null" title="Request a change" @close="amendSheet = null">
            <p class="help mb-4">
                Describe what you would like changed. Be as specific as you can — quoting the clause helps.
            </p>
            <FormField id="a-body" label="What should change?" required>
                <textarea id="a-body" v-model="amendBody" class="field min-h-[160px]"></textarea>
            </FormField>
            <p v-if="amendSheet && amendSheet.amendments_used >= allowance" class="rounded-md border border-attention-border bg-attention-bg p-3 text-body-s text-ink">
                This is beyond your included allowance. The team will confirm any fee before doing the work
                — nothing is charged without your approval.
            </p>
            <template #actions>
                <button type="button" class="btn btn-primary" :disabled="!amendBody.trim()" @click="requestAmendment">Send request</button>
                <button type="button" class="btn btn-tertiary" @click="amendSheet = null">Cancel</button>
            </template>
        </Sheet>

        <Sheet :open="approveSheet !== null" title="Approve the final wording" @close="approveSheet = null">
            <p class="prose-measure mb-4 text-body leading-[1.65] text-ink">
                Please read the draft in full, including the schedules, before you approve it.
            </p>
            <div class="card-paper mb-4 border border-rule-warm p-4">
                <p class="legal-measure text-ink">
                    Approving the wording is your confirmation that the document says what you intend.
                    It does not register the Will and does not commit any authority. Registration is
                    completed by the competent authority under its own current requirements.
                </p>
            </div>
            <label class="flex items-start gap-2.5">
                <input v-model="confirmed" type="checkbox" class="tap mt-0.5 accent-gold">
                <span class="text-legal leading-[1.72] text-ink">
                    I have read the draft in full and I approve this wording.
                </span>
            </label>
            <template #actions>
                <button type="button" class="btn btn-primary" :disabled="!confirmed" @click="approve">Record my approval</button>
                <button type="button" class="btn btn-tertiary" @click="approveSheet = null">Not yet</button>
            </template>
        </Sheet>
    </ClientLayout>
</template>
