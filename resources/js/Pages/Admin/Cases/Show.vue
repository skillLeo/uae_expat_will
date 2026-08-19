<script setup>
/**
 * Case detail.
 *
 * When the viewer lacks cases.view_restricted the body is redacted — reference
 * and status only. The redaction happens server-side; this component simply
 * renders what it was given, so there is no client-side secret to leak.
 */
import AdminLayout from '@/Layouts/AdminLayout.vue';
import StatusPill from '@/Components/StatusPill.vue';

defineProps({
    record: { type: Object, required: true },
    readable: { type: Boolean, default: true },
    triggerReasons: { type: Array, default: () => [] },
    answers: { type: Array, default: () => [] },
    statusHistory: { type: Array, default: () => [] },
    notes: { type: Array, default: () => [] },
    internalStatuses: { type: Array, default: () => [] },
});
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
                            <dt class="text-slate">Quoted</dt>
                            <dd class="tabular text-right font-mono">{{ record.currency }} {{ record.quoted_amount }}</dd>
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
            </aside>
        </div>
    </AdminLayout>
</template>
