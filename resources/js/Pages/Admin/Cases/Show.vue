<script setup>
/**
 * Case detail.
 *
 * When the viewer lacks cases.view_restricted the body is redacted — reference
 * and status only. The redaction happens server-side; this component simply
 * renders what it was given, so there is no client-side secret to leak.
 */
import { ref, reactive } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import StatusPill from '@/Components/StatusPill.vue';
import Sheet from '@/Components/Sheet.vue';
import FormField from '@/Components/FormField.vue';

const props = defineProps({
    record: { type: Object, required: true },
    readable: { type: Boolean, default: true },
    triggerReasons: { type: Array, default: () => [] },
    answers: { type: Array, default: () => [] },
    statusHistory: { type: Array, default: () => [] },
    notes: { type: Array, default: () => [] },
    contacts: { type: Array, default: () => [] },
    payments: { type: Array, default: () => [] },
    drafts: { type: Array, default: () => [] },
    documents: { type: Array, default: () => [] },
    stages: { type: Array, default: () => [] },
    staff: { type: Array, default: () => [] },
    internalStatuses: { type: Array, default: () => [] },
    paymentTypes: { type: Array, default: () => [] },
});

const page = usePage();
const can = (p) => (page.props.auth?.permissions ?? []).includes(p);

const sheet = ref(null);
const money = (n) => Number(n ?? 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

const assignForm = reactive({ assigned_to: '', reason: '' });
const statusForm = reactive({ internal_status: props.record.internal_status, reason: '' });
const noteForm = reactive({ body: '', is_internal: true });
const contactForm = reactive({ channel: 'phone', direction: 'outbound', summary: '' });
// The type is chosen, not typed. Free text was the reason an authority charge
// and Summit's own fee were indistinguishable to everything downstream.
const linkForm = reactive({ amount: props.record.quoted_amount ?? '', type: 'professional_fee', stage_label: '' });
const manualForm = reactive({ amount: props.record.quoted_amount ?? '', method: 'bank_transfer', type: 'professional_fee', stage_label: '', reference: '' });

const describeType = (value) => props.paymentTypes.find((t) => t.value === value)?.description ?? '';

// Only an authority charge needs describing. "Professional fee" says everything
// there is to say; "authority charge" does not say which authority, or for what.
const needsDescription = (form) => form.type === 'disbursement';

const post = (url, data) => router.post(url, data, { preserveScroll: true, onSuccess: () => { sheet.value = null; } });

const draftFile = ref(null);
const docFile = ref(null);
const docCategory = ref('other');
const reviewing = ref(null);
const reviewNote = ref('');

function uploadDraft(event) {
    const file = event.target.files?.[0];
    if (!file) return;
    router.post(`/admin/cases/${props.record.id}/drafts`, { file }, {
        preserveScroll: true, forceFormData: true,
        onFinish: () => { if (draftFile.value) draftFile.value.value = ''; },
    });
}

function uploadDocument(event) {
    const file = event.target.files?.[0];
    if (!file) return;
    router.post(`/admin/cases/${props.record.id}/documents`, { file, category: docCategory.value }, {
        preserveScroll: true, forceFormData: true,
        onFinish: () => { if (docFile.value) docFile.value.value = ''; },
    });
}

const sendDraft = (d) => confirm(`Send draft ${d.version_number} to the customer? This records the first-draft stage, which moves the refund band.`)
    && post(`/admin/drafts/${d.id}/send`, {});

const reviewDocument = (status) => router.post(`/admin/documents/${reviewing.value.id}/review`, {
    status, review_note: reviewNote.value || null,
}, {
    preserveScroll: true,
    onSuccess: () => { reviewing.value = null; reviewNote.value = ''; },
});

const resolveAmendment = (a) => post(`/admin/amendments/${a.id}/resolve`, {});

const draftTone = (status) => ({
    draft: 'neutral', sent: 'progress', amendments_requested: 'attention', approved: 'positive',
}[status] ?? 'neutral');

const docTone = (status) => ({ pending: 'attention', accepted: 'positive', rejected: 'critical' }[status] ?? 'neutral');
</script>

<template>
    <AdminLayout :title="record.reference" back-href="/admin/cases">
        <div class="grid grid-cols-[minmax(0,1fr)_360px] gap-6 max-[1080px]:grid-cols-1">
            <div class="min-w-0">
                <!-- The restricted banner. Present, never hidden. -->
                <div v-if="record.is_restricted" class="mb-6 rounded-md border border-held-border bg-held-bg p-4">
                    <div class="mb-1.5 flex items-center gap-2">
                        <svg width="17" height="17" viewBox="0 0 16 16" fill="none" stroke="#63467F" stroke-width="1.6" class="flex-none" aria-hidden="true">
                            <rect x="3.6" y="7.2" width="8.8" height="6.4" rx="1" /><path d="M5.6 7.2V5.6a2.4 2.4 0 0 1 4.8 0v1.6" />
                        </svg>
                        <span class="text-body-s font-semibold text-held">Restricted — authorised legal staff only</span>
                    </div>
                    <p v-if="record.restricted_reason" class="text-legal leading-[1.72] text-ink">{{ record.restricted_reason }}</p>
                    <p v-else class="text-legal leading-[1.72] text-ink">
                        This matter carries a restricted flag. Its content is visible only to authorised
                        legal staff. This is not a rejection and the customer has not been told a reason.
                    </p>
                </div>

                <section class="card mb-6 p-6">
                    <h2 class="mb-4 text-h3 font-semibold text-ink">Why this case landed here</h2>
                    <p v-if="!triggerReasons.length" class="help">No routing triggers fired — the case continued online.</p>
                    <ol v-else class="grid gap-3">
                        <li v-for="(reason, i) in triggerReasons" :key="i" class="border-b border-rule-cool pb-3 last:border-0">
                            <div class="mb-1 flex flex-wrap items-center gap-2">
                                <span class="tabular font-mono text-caption text-gold-strong">{{ String(i + 1).padStart(2, '0') }}</span>
                                <StatusPill :tone="reason.outcome === 'urgent_review' ? 'held' : reason.outcome === 'review' ? 'held' : 'positive'" :label="reason.outcome" />
                            </div>
                            <div class="text-body-s font-medium text-ink">{{ reason.question_prompt }}</div>
                            <div class="text-body-s text-ink-70">{{ reason.answer_label }}</div>
                        </li>
                    </ol>
                </section>

                <section v-if="readable && answers.length" class="card mb-6 p-6">
                    <h2 class="mb-4 text-h3 font-semibold text-ink">Assessment answers</h2>
                    <dl class="grid gap-3">
                        <div v-for="a in answers" :key="a.key" class="border-b border-rule-cool pb-3 last:border-0">
                            <dt class="text-caption text-slate">
                                {{ a.prompt }}
                                <span v-if="a.is_sensitive" class="pill pill-neutral ml-1.5">sensitive</span>
                            </dt>
                            <dd class="text-body-s font-medium text-ink">{{ a.answer }}</dd>
                        </div>
                    </dl>
                </section>

                <section v-else-if="!readable" class="card mb-6 p-6">
                    <p class="text-body-s text-slate">
                        The body of this case is not visible to you. Reference and status only.
                    </p>
                </section>

                <section class="card p-6">
                    <h2 class="mb-4 text-h3 font-semibold text-ink">Status history</h2>
                    <ol class="grid gap-3">
                        <li v-for="(h, i) in statusHistory" :key="i" class="border-b border-rule-cool pb-3 last:border-0">
                            <div class="text-body-s font-medium text-ink">{{ h.to }}</div>
                            <div class="text-caption text-slate">
                                {{ new Date(h.at).toLocaleString('en-GB') }}<span v-if="h.by"> · {{ h.by }}</span>
                            </div>
                            <div v-if="h.reason" class="text-caption text-ink-70">{{ h.reason }}</div>
                        </li>
                    </ol>
                </section>
            </div>

            <aside class="grid content-start gap-4">
                <div class="card p-6">
                    <div class="eyebrow mb-2">Status</div>
                    <StatusPill :tone="record.tone" :label="record.status_label" class="mb-3" />
                    <dl class="grid gap-2 text-body-s">
                        <div class="flex justify-between gap-3">
                            <dt class="text-slate">Internal</dt>
                            <dd class="text-right font-medium text-ink">{{ record.internal_status_label }}</dd>
                        </div>
                        <div class="flex justify-between gap-3">
                            <dt class="text-slate">Assigned</dt>
                            <dd class="text-right">{{ record.assignee ?? 'Unassigned' }}</dd>
                        </div>
                        <div v-if="record.quoted_amount" class="flex justify-between gap-3">
                            <dt class="text-slate">Professional fee quoted</dt>
                            <dd class="tabular text-right font-mono">{{ record.currency }} {{ record.quoted_amount }}</dd>
                        </div>
                        <div class="flex justify-between gap-3">
                            <dt class="text-slate">Professional fee paid</dt>
                            <dd class="tabular text-right font-mono">{{ record.currency }} {{ money(record.paid_amount) }}</dd>
                        </div>
                        <!-- Kept on its own line. This money is collected for
                             somebody else and is not measured against the quote. -->
                        <div v-if="record.disbursements_paid" class="flex justify-between gap-3">
                            <dt class="text-slate">Authority charges paid</dt>
                            <dd class="tabular text-right font-mono">{{ record.currency }} {{ money(record.disbursements_paid) }}</dd>
                        </div>
                        <div class="flex justify-between gap-3">
                            <dt class="text-slate">Payment</dt>
                            <dd class="text-right">{{ record.allows_payment ? 'Permitted' : 'Not permitted' }}</dd>
                        </div>
                    </dl>
                    <p v-if="!record.allows_payment" class="help mt-3 border-t border-rule-cool pt-3">
                        No payment may be requested while this matter is held.
                    </p>
                </div>

                <div v-if="readable" class="card p-6">
                    <div class="eyebrow mb-2">Customer</div>
                    <div class="text-body-s font-medium text-ink">{{ record.customer?.name ?? '—' }}</div>
                    <div class="text-caption text-slate">{{ record.customer?.email }}</div>
                </div>

                <!-- Countdown -->
                <div v-if="record.countdown_due_at" class="card p-6" :class="record.is_overdue ? 'border-critical-border bg-critical-bg' : ''">
                    <div class="eyebrow mb-2">First contact due</div>
                    <div class="tabular font-mono text-body" :class="record.is_overdue ? 'text-critical' : 'text-ink'">
                        {{ new Date(record.countdown_due_at).toLocaleString('en-GB') }}
                    </div>
                    <p v-if="record.is_overdue" class="mt-1.5 text-caption font-medium text-critical">Overdue. Logging a contact clears this.</p>
                </div>

                <!-- Actions -->
                <div class="card p-6">
                    <div class="eyebrow mb-3">Actions</div>
                    <div class="grid gap-2">
                        <button v-if="can('cases.assign')" type="button" class="btn btn-sm btn-secondary" @click="sheet = 'assign'">Assign staff</button>
                        <button v-if="can('cases.update')" type="button" class="btn btn-sm btn-secondary" @click="sheet = 'status'">Change status</button>
                        <button v-if="can('notes.create')" type="button" class="btn btn-sm btn-secondary" @click="sheet = 'note'">Add note</button>
                        <button v-if="can('contacts.log')" type="button" class="btn btn-sm btn-secondary" @click="sheet = 'contact'">Log contact</button>
                        <button v-if="can('payments.create_link') && record.allows_payment" type="button" class="btn btn-sm btn-secondary" @click="sheet = 'link'">Generate payment link</button>
                        <button v-if="can('payments.record_manual') && record.allows_payment" type="button" class="btn btn-sm btn-secondary" @click="sheet = 'manual'">Record manual payment</button>
                        <button v-if="can('cases.update')" type="button" class="btn btn-sm btn-secondary" @click="post(`/admin/cases/${record.id}/magic-link`, {})">Issue questionnaire link</button>
                    </div>
                    <p v-if="!record.allows_payment" class="help mt-3 border-t border-rule-cool pt-3">
                        No payment control is offered while this matter is held.
                    </p>
                </div>

                <!-- Payments -->
                <div v-if="payments.length" class="card p-6">
                    <div class="eyebrow mb-3">Payments</div>
                    <div class="grid gap-2">
                        <div v-for="p in payments" :key="p.id" class="border-b border-rule-cool pb-2 last:border-0">
                            <div class="flex items-center justify-between gap-3">
                                <span class="text-body-s text-ink">{{ p.stage_label }}</span>
                                <StatusPill :tone="p.tone" :label="p.status_label" />
                            </div>
                            <div class="mb-0.5">
                                <span class="pill" :class="p.type === 'disbursement' ? 'pill-attention' : 'pill-neutral'">{{ p.type_label }}</span>
                            </div>
                            <div class="tabular font-mono text-caption text-slate">{{ p.currency }} {{ money(p.total_amount) }}</div>
                            <a v-if="p.link_url && p.status === 'pending'" :href="p.link_url" target="_blank" rel="noopener" class="text-caption text-gold-strong underline">Open payment link</a>
                        </div>
                    </div>
                </div>

                <!-- Stage timestamps: what the refund band is computed from -->
                <div class="card p-6">
                    <div class="eyebrow mb-1">Stages reached</div>
                    <p class="help mb-3">The refund band is computed from these, and nothing else.</p>
                    <div class="grid gap-1.5">
                        <div v-for="s in stages" :key="s.value" class="flex items-center justify-between gap-3 text-body-s">
                            <span :class="s.reached_at ? 'text-ink' : 'text-slate'">{{ s.label }}</span>
                            <button
                                v-if="!s.reached_at && can('cases.update')" type="button"
                                class="text-caption font-medium text-gold-strong"
                                @click="post(`/admin/cases/${record.id}/stage`, { stage: s.value })"
                            >record</button>
                            <span v-else-if="s.reached_at" class="tabular font-mono text-caption text-slate">{{ new Date(s.reached_at).toLocaleDateString('en-GB') }}</span>
                        </div>
                    </div>
                </div>
            </aside>
        </div>

        <!-- Drafts and documents -->
        <div v-if="readable" class="mt-6 grid grid-cols-2 gap-6 max-[1080px]:grid-cols-1">
            <section class="card p-6">
                <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                    <h2 class="text-h3 font-semibold text-ink">Drafts</h2>
                    <label v-if="can('drafts.send')" class="btn btn-sm btn-secondary cursor-pointer">
                        Upload a draft
                        <input ref="draftFile" type="file" accept=".pdf,.doc,.docx" class="sr-only" @change="uploadDraft">
                    </label>
                </div>

                <p v-if="!drafts.length" class="help">
                    No draft yet. Uploading one does not release it — you send it separately.
                </p>

                <article v-for="d in drafts" :key="d.id" class="mb-3 border-b border-rule-cool pb-3 last:mb-0 last:border-0 last:pb-0">
                    <div class="mb-2 flex flex-wrap items-center justify-between gap-2">
                        <span class="text-body-s font-medium text-ink">Draft {{ d.version_number }}</span>
                        <StatusPill :tone="draftTone(d.status)" :label="d.status.replace(/_/g, ' ')" />
                    </div>
                    <div class="tabular mb-2 font-mono text-caption text-slate">
                        <span v-if="d.sent_at">sent {{ new Date(d.sent_at).toLocaleDateString('en-GB') }}</span>
                        <span v-if="d.approved_at"> · approved {{ new Date(d.approved_at).toLocaleDateString('en-GB') }}</span>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <a v-if="d.url" :href="d.url" class="btn btn-sm btn-tertiary" target="_blank" rel="noopener">Open</a>
                        <button
                            v-if="can('drafts.send') && d.status === 'draft' && d.has_file"
                            type="button" class="btn btn-sm btn-primary" @click="sendDraft(d)"
                        >Send to customer</button>
                    </div>

                    <div v-if="d.amendments.length" class="mt-3 border-t border-rule-cool pt-3">
                        <div class="eyebrow mb-2">Amendment requests</div>
                        <div v-for="a in d.amendments" :key="a.id" class="mb-2 last:mb-0">
                            <p class="whitespace-pre-line text-body-s text-ink">{{ a.body }}</p>
                            <div class="mt-1 flex flex-wrap items-center gap-2">
                                <span class="tabular font-mono text-caption text-slate">{{ new Date(a.at).toLocaleDateString('en-GB') }}</span>
                                <span v-if="!a.within_allowance" class="pill pill-attention">beyond allowance</span>
                                <span v-if="a.status === 'resolved'" class="pill pill-positive">resolved</span>
                                <button
                                    v-else-if="can('drafts.send')" type="button"
                                    class="text-caption font-medium text-gold-strong" @click="resolveAmendment(a)"
                                >mark resolved</button>
                            </div>
                        </div>
                    </div>
                </article>
            </section>

            <section class="card p-6">
                <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                    <h2 class="text-h3 font-semibold text-ink">Documents</h2>
                    <label v-if="can('documents.upload')" class="btn btn-sm btn-secondary cursor-pointer">
                        Add a document
                        <input ref="docFile" type="file" accept=".pdf,.jpg,.jpeg,.png,.heic,.webp,.doc,.docx" class="sr-only" @change="uploadDocument">
                    </label>
                </div>

                <p v-if="!documents.length" class="help">Nothing uploaded yet.</p>

                <article v-for="doc in documents" :key="doc.id" class="mb-3 border-b border-rule-cool pb-3 last:mb-0 last:border-0 last:pb-0">
                    <div class="mb-1 flex flex-wrap items-center justify-between gap-2">
                        <span class="text-body-s font-medium text-ink">{{ doc.category.replace(/_/g, ' ') }}</span>
                        <StatusPill :tone="docTone(doc.status)" :label="doc.status" />
                    </div>
                    <div class="tabular truncate font-mono text-caption text-slate">{{ doc.filename }}</div>
                    <p v-if="doc.review_note" class="mt-1 text-caption text-attention">{{ doc.review_note }}</p>
                    <div class="mt-2 flex flex-wrap gap-2">
                        <a v-if="doc.url" :href="doc.url" class="btn btn-sm btn-tertiary" target="_blank" rel="noopener">Open</a>
                        <button
                            v-if="can('documents.upload')" type="button" class="btn btn-sm btn-tertiary"
                            @click="reviewing = doc; reviewNote = doc.review_note ?? ''"
                        >Review</button>
                    </div>
                </article>
            </section>
        </div>

        <!-- Notes and contact history -->
        <div v-if="readable" class="mt-6 grid grid-cols-2 gap-6 max-[1080px]:grid-cols-1">
            <section class="card p-6">
                <h2 class="mb-4 text-h3 font-semibold text-ink">Notes</h2>
                <div class="grid gap-3">
                    <article v-for="n in notes" :key="n.id" class="border-b border-rule-cool pb-3 last:border-0">
                        <p class="mb-1 whitespace-pre-line text-body-s text-ink">{{ n.body }}</p>
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="tabular font-mono text-caption text-slate">{{ n.author }} · {{ new Date(n.at).toLocaleString('en-GB') }}</span>
                            <span v-if="n.is_internal" class="pill pill-neutral">internal</span>
                        </div>
                    </article>
                    <p v-if="!notes.length" class="help">No notes yet.</p>
                </div>
            </section>

            <section class="card p-6">
                <h2 class="mb-4 text-h3 font-semibold text-ink">Contact history</h2>
                <div class="grid gap-3">
                    <article v-for="(c, i) in contacts" :key="i" class="border-b border-rule-cool pb-3 last:border-0">
                        <div class="mb-1 flex flex-wrap items-center gap-2">
                            <span class="pill pill-progress">{{ c.direction }} {{ c.channel }}</span>
                            <span class="tabular font-mono text-caption text-slate">{{ new Date(c.at).toLocaleString('en-GB') }}</span>
                        </div>
                        <p v-if="c.summary" class="text-body-s text-ink">{{ c.summary }}</p>
                    </article>
                    <p v-if="!contacts.length" class="help">No contact logged yet.</p>
                </div>
            </section>
        </div>

        <!-- Action sheets -->
        <Sheet :open="sheet === 'assign'" title="Assign this case" size="sm" @close="sheet = null">
            <FormField id="a-user" label="Assign to">
                <select id="a-user" v-model="assignForm.assigned_to" class="field">
                    <option value="">Unassigned</option>
                    <option v-for="u in staff" :key="u.id" :value="u.id">{{ u.name }}</option>
                </select>
            </FormField>
            <FormField id="a-reason" label="Reason" :required="!!record.assignee" help="Required when reassigning.">
                <textarea id="a-reason" v-model="assignForm.reason" class="field min-h-[70px]"></textarea>
            </FormField>
            <template #actions>
                <button type="button" class="btn btn-primary" @click="post(`/admin/cases/${record.id}/assign`, assignForm)">Save</button>
                <button type="button" class="btn btn-tertiary" @click="sheet = null">Cancel</button>
            </template>
        </Sheet>

        <Sheet :open="sheet === 'status'" title="Change status" @close="sheet = null">
            <p class="help mb-4">
                The customer only ever sees the group in brackets. The internal label is never shown to them.
            </p>
            <FormField id="s-status" label="Internal status" required>
                <select id="s-status" v-model="statusForm.internal_status" class="field">
                    <option v-for="s in internalStatuses" :key="s.value" :value="s.value">{{ s.label }} ({{ s.group }})</option>
                </select>
            </FormField>
            <FormField id="s-reason" label="Reason"><textarea id="s-reason" v-model="statusForm.reason" class="field min-h-[70px]"></textarea></FormField>
            <template #actions>
                <button type="button" class="btn btn-primary" @click="post(`/admin/cases/${record.id}/status`, statusForm)">Change status</button>
                <button type="button" class="btn btn-tertiary" @click="sheet = null">Cancel</button>
            </template>
        </Sheet>

        <Sheet :open="sheet === 'note'" title="Add a note" @close="sheet = null">
            <FormField id="n-body" label="Note" required><textarea id="n-body" v-model="noteForm.body" class="field min-h-[140px]"></textarea></FormField>
            <label class="flex items-center gap-2.5"><input v-model="noteForm.is_internal" type="checkbox" class="tap accent-gold"><span class="text-body-s">Internal only</span></label>
            <template #actions>
                <button type="button" class="btn btn-primary" @click="post(`/admin/cases/${record.id}/notes`, noteForm)">Add note</button>
                <button type="button" class="btn btn-tertiary" @click="sheet = null">Cancel</button>
            </template>
        </Sheet>

        <Sheet :open="sheet === 'contact'" title="Log a contact" @close="sheet = null">
            <div class="grid grid-cols-2 gap-4 max-[719px]:grid-cols-1">
                <FormField id="c-channel" label="Channel" required>
                    <select id="c-channel" v-model="contactForm.channel" class="field">
                        <option value="phone">Phone</option><option value="email">Email</option>
                        <option value="whatsapp">WhatsApp</option><option value="meeting">Meeting</option>
                    </select>
                </FormField>
                <FormField id="c-dir" label="Direction" required>
                    <select id="c-dir" v-model="contactForm.direction" class="field">
                        <option value="outbound">Outbound</option><option value="inbound">Inbound</option>
                    </select>
                </FormField>
            </div>
            <FormField id="c-summary" label="Summary"><textarea id="c-summary" v-model="contactForm.summary" class="field min-h-[100px]"></textarea></FormField>
            <p class="help">Logging a contact clears the countdown.</p>
            <template #actions>
                <button type="button" class="btn btn-primary" @click="post(`/admin/cases/${record.id}/contacts`, contactForm)">Log contact</button>
                <button type="button" class="btn btn-tertiary" @click="sheet = null">Cancel</button>
            </template>
        </Sheet>

        <Sheet :open="sheet === 'link'" title="Generate a payment link" size="sm" @close="sheet = null">
            <FormField id="l-amount" label="Amount excluding VAT" required>
                <input id="l-amount" v-model="linkForm.amount" type="number" step="0.01" class="field" inputmode="decimal">
            </FormField>
            <FormField id="l-type" label="What is this payment for?" required>
                <select id="l-type" v-model="linkForm.type" class="field">
                    <option v-for="t in paymentTypes" :key="t.value" :value="t.value">{{ t.label }}</option>
                </select>
            </FormField>
            <p class="help -mt-2 mb-4">{{ describeType(linkForm.type) }}</p>
            <FormField v-if="needsDescription(linkForm)" id="l-stage" label="Which charge, and to whom?" required>
                <input id="l-stage" v-model="linkForm.stage_label" class="field" placeholder="DIFC Wills Service Centre registration fee">
            </FormField>
            <p class="help">VAT is added automatically at the configured rate.</p>
            <template #actions>
                <button type="button" class="btn btn-primary" @click="post(`/admin/cases/${record.id}/payment-link`, linkForm)">Generate link</button>
                <button type="button" class="btn btn-tertiary" @click="sheet = null">Cancel</button>
            </template>
        </Sheet>

        <Sheet :open="reviewing !== null" title="Review this document" size="sm" @close="reviewing = null">
            <p class="help mb-4">
                The note below is shown to the customer. A rejection without one leaves them guessing
                what to send instead, so it is required.
            </p>
            <FormField id="rev-note" label="Note to the customer">
                <textarea id="rev-note" v-model="reviewNote" class="field min-h-[90px]" placeholder="The passport scan is cut off at the bottom — please resend the full page."></textarea>
            </FormField>
            <template #actions>
                <button type="button" class="btn btn-primary" @click="reviewDocument('accepted')">Accept</button>
                <button type="button" class="btn btn-destructive" :disabled="!reviewNote.trim()" @click="reviewDocument('rejected')">Reject</button>
                <button type="button" class="btn btn-tertiary" @click="reviewing = null">Cancel</button>
            </template>
        </Sheet>

        <Sheet :open="sheet === 'manual'" title="Record a manual payment" size="sm" @close="sheet = null">
            <FormField id="m-amount" label="Amount excluding VAT" required>
                <input id="m-amount" v-model="manualForm.amount" type="number" step="0.01" class="field" inputmode="decimal">
            </FormField>
            <FormField id="m-method" label="Method" required>
                <select id="m-method" v-model="manualForm.method" class="field">
                    <option value="bank_transfer">Bank transfer</option><option value="cash">Cash</option>
                </select>
            </FormField>
            <FormField id="m-type" label="What is this payment for?" required>
                <select id="m-type" v-model="manualForm.type" class="field">
                    <option v-for="t in paymentTypes" :key="t.value" :value="t.value">{{ t.label }}</option>
                </select>
            </FormField>
            <p class="help -mt-2 mb-4">{{ describeType(manualForm.type) }}</p>
            <FormField v-if="needsDescription(manualForm)" id="m-stage" label="Which charge, and to whom?" required>
                <input id="m-stage" v-model="manualForm.stage_label" class="field" placeholder="DIFC Wills Service Centre registration fee">
            </FormField>
            <p v-if="needsDescription(manualForm)" class="help -mt-2 mb-4">
                Recording this marks the third-party cost as committed, which moves the matter into refund band D.
            </p>
            <FormField id="m-ref" label="Reference"><input id="m-ref" v-model="manualForm.reference" class="field"></FormField>
            <template #actions>
                <button type="button" class="btn btn-primary" @click="post(`/admin/cases/${record.id}/manual-payment`, manualForm)">Record payment</button>
                <button type="button" class="btn btn-tertiary" @click="sheet = null">Cancel</button>
            </template>
        </Sheet>
    </AdminLayout>
</template>
