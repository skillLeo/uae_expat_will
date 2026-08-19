<script setup>
/**
 * The customer dashboard.
 *
 * Shows only the eight customer-facing statuses. The internal status never
 * reaches this component because it never leaves the server.
 */
import { Link } from '@inertiajs/vue3';
import ClientLayout from '@/Layouts/ClientLayout.vue';
import StatusPill from '@/Components/StatusPill.vue';

defineProps({ cases: { type: Array, default: () => [] } });

const money = (n) => Number(n ?? 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
</script>

<template>
    <ClientLayout title="Your matters">
        <div v-if="!cases.length" class="card p-8 text-center">
            <h2 class="mb-2 text-h3 font-semibold text-ink">No matters yet</h2>
            <p class="help mb-5">Once you complete the assessment, your matter appears here.</p>
            <Link href="/assessment" class="btn btn-primary">Start the assessment</Link>
        </div>

        <div v-for="c in cases" :key="c.id" class="mb-6 grid grid-cols-[minmax(0,1fr)_340px] gap-6 max-[1080px]:grid-cols-1">
            <article class="card p-6 max-[719px]:p-4">
                <div class="mb-4 flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <div class="eyebrow mb-1">Reference</div>
                        <div class="tabular font-mono text-h3 text-ink">{{ c.reference }}</div>
                    </div>
                    <StatusPill :tone="c.tone" :label="c.status_label" />
                </div>

                <!-- The eight-stage tracker. Done, current and future each carry
                     a distinct icon as well as a colour. -->
                <ol class="grid gap-2">
                    <li v-for="(s, i) in c.stages" :key="i" class="flex items-start gap-3">
                        <span class="mt-0.5 flex-none" aria-hidden="true">
                            <svg v-if="s.state === 'done'" width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="#2E6A4E" stroke-width="2">
                                <polyline points="3.6,8.4 6.4,11.2 12.4,4.8" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                            <svg v-else-if="s.state === 'current'" width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="#AD8A46" stroke-width="1.8">
                                <circle cx="8" cy="8" r="6" /><circle cx="8" cy="8" r="2" fill="#AD8A46" stroke="none" />
                            </svg>
                            <svg v-else width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="#B9C2D1" stroke-width="1.5">
                                <circle cx="8" cy="8" r="6" stroke-dasharray="2 2" />
                            </svg>
                        </span>
                        <span
                            class="text-body-s leading-[1.5]"
                            :class="{
                                'text-ink-70': s.state === 'done',
                                'font-semibold text-ink': s.state === 'current',
                                'text-slate': s.state === 'upcoming',
                            }"
                            :aria-current="s.state === 'current' ? 'step' : undefined"
                        >{{ s.label }}</span>
                    </li>
                </ol>
            </article>

            <aside class="grid content-start gap-4">
                <!-- The payment control EXISTS only where payment is permitted. -->
                <div v-if="c.allows_payment && c.has_outstanding_payment" class="card card-accent p-5">
                    <div class="eyebrow mb-2">Payment due</div>
                    <div class="tabular mb-3 font-mono text-h2 text-ink">{{ c.currency }} {{ money(c.quoted_amount) }}</div>
                    <a v-if="c.payment_link" :href="c.payment_link" class="btn btn-primary w-full">Pay securely</a>
                    <p class="help mt-3">
                        Card details are entered on the gateway's own page. We never see or store them.
                    </p>
                </div>
                <div v-else-if="!c.allows_payment" class="card p-5">
                    <div class="eyebrow mb-2">Payment</div>
                    <p class="text-body-s leading-[1.6] text-ink">
                        Nothing is payable while your matter is with our legal team. We will explain
                        the service and any fee before anything is charged.
                    </p>
                </div>

                <div class="card p-5">
                    <div class="eyebrow mb-3">Continue</div>
                    <div class="grid gap-2">
                        <Link :href="`/client/cases/${c.id}/questionnaire`" class="btn btn-sm btn-secondary">Detailed questionnaire</Link>
                        <Link :href="`/client/cases/${c.id}/documents`" class="btn btn-sm btn-secondary">
                            Documents <span v-if="c.documents_count" class="tabular font-mono">({{ c.documents_count }})</span>
                        </Link>
                        <Link v-if="c.latest_draft" :href="`/client/cases/${c.id}/drafts`" class="btn btn-sm btn-secondary">
                            Draft {{ c.latest_draft.version_number }}
                        </Link>
                    </div>
                </div>
            </aside>
        </div>
    </ClientLayout>
</template>
