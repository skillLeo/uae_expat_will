<script setup>
/**
 * Operational analytics.
 *
 * Everything here is aggregate. Abandonment is reported by question, never by
 * what anyone answered — no answer content, religion, family or beneficiary
 * detail reaches this screen.
 */
import { computed } from 'vue';
import { router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import StatusPill from '@/Components/StatusPill.vue';

const props = defineProps({
    range: { type: Object, required: true },
    leads: { type: Object, default: () => ({}) },
    outcomes: { type: Array, default: () => [] },
    abandonment: { type: Array, default: () => [] },
    conversion: { type: Object, default: () => ({}) },
    revenue: { type: Object, default: () => ({}) },
    pipeline: { type: Array, default: () => [] },
});

const money = (n) => Number(n ?? 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const pct = (a, b) => (b > 0 ? Math.round((a / b) * 100) : 0);

const maxOf = (rows, key = 'total') => Math.max(1, ...rows.map((r) => r[key] ?? 0));

const setRange = (days) => router.get('/admin/analytics', { days }, { preserveState: true });
</script>

<template>
    <AdminLayout title="Analytics">
        <template #action>
            <select class="field btn-sm max-w-[160px]" :value="range.days" @change="setRange($event.target.value)">
                <option :value="30">Last 30 days</option>
                <option :value="90">Last 90 days</option>
                <option :value="365">Last year</option>
            </select>
        </template>

        <!-- Funnel -->
        <section class="mb-8">
            <h2 class="mb-3 text-h4 font-semibold text-ink">Conversion</h2>
            <div class="grid grid-cols-4 gap-4 max-[900px]:grid-cols-2">
                <div v-for="[label, value, of] in [
                    ['Assessments started', conversion.started, null],
                    ['Completed', conversion.completed, conversion.started],
                    ['Cases opened', conversion.cases, conversion.completed],
                    ['Paid', conversion.paid, conversion.cases],
                ]" :key="label" class="card p-4">
                    <div class="eyebrow mb-2">{{ label }}</div>
                    <div class="tabular font-mono text-display-s text-ink">{{ value ?? 0 }}</div>
                    <div v-if="of" class="tabular help mt-1">{{ pct(value, of) }}% of previous step</div>
                </div>
            </div>
        </section>

        <div class="grid grid-cols-2 gap-6 max-[1080px]:grid-cols-1">
            <!-- Outcome split -->
            <section class="card p-5">
                <h2 class="mb-4 text-h4 font-semibold text-ink">Outcome split</h2>
                <div class="grid gap-2.5">
                    <div v-for="o in outcomes" :key="o.value">
                        <div class="mb-1 flex items-center justify-between gap-3">
                            <StatusPill :tone="o.tone" :label="o.label" />
                            <span class="tabular font-mono text-body-s text-ink">{{ o.total }}</span>
                        </div>
                        <div class="h-1.5 w-full rounded-pill bg-neutral-bg">
                            <div class="h-full rounded-pill bg-gold" :style="{ width: `${(o.total / maxOf(outcomes)) * 100}%` }"></div>
                        </div>
                    </div>
                    <p v-if="!outcomes.length" class="help">No completed assessments in this range.</p>
                </div>
            </section>

            <!-- Leads -->
            <section class="card p-5">
                <h2 class="mb-4 text-h4 font-semibold text-ink">Leads by source</h2>
                <div class="grid gap-2.5">
                    <div v-for="s in leads.by_source" :key="s.label">
                        <div class="mb-1 flex items-center justify-between gap-3">
                            <span class="text-body-s text-ink">{{ s.label }}</span>
                            <span class="tabular font-mono text-body-s text-ink">{{ s.total }}</span>
                        </div>
                        <div class="h-1.5 w-full rounded-pill bg-neutral-bg">
                            <div class="h-full rounded-pill bg-progress" :style="{ width: `${(s.total / maxOf(leads.by_source)) * 100}%` }"></div>
                        </div>
                    </div>
                    <p v-if="!leads.by_source?.length" class="help">No leads recorded.</p>
                </div>

                <h3 class="mb-3 mt-6 text-body-s font-semibold text-ink">By campaign</h3>
                <div class="grid gap-1.5">
                    <div v-for="c in leads.by_campaign" :key="c.label" class="flex justify-between gap-3 text-body-s">
                        <span class="text-ink-70">{{ c.label }}</span>
                        <span class="tabular font-mono text-ink">{{ c.total }}</span>
                    </div>
                    <p v-if="!leads.by_campaign?.length" class="help">No campaign attribution recorded.</p>
                </div>
            </section>

            <!-- Abandonment -->
            <section class="card p-5">
                <h2 class="mb-1 text-h4 font-semibold text-ink">Where people stop</h2>
                <p class="help mb-4">By question. No answer content is recorded here.</p>
                <div class="grid gap-2.5">
                    <div v-for="a in abandonment" :key="a.key">
                        <div class="mb-1 flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <code class="font-mono text-caption text-gold-strong">{{ a.key }}</code>
                                <p class="truncate text-caption text-ink-70">{{ a.prompt }}</p>
                            </div>
                            <span class="tabular flex-none font-mono text-body-s text-ink">{{ a.total }}</span>
                        </div>
                        <div class="h-1.5 w-full rounded-pill bg-neutral-bg">
                            <div class="h-full rounded-pill bg-attention" :style="{ width: `${(a.total / maxOf(abandonment)) * 100}%` }"></div>
                        </div>
                    </div>
                    <p v-if="!abandonment.length" class="help">No abandoned assessments in this range.</p>
                </div>
            </section>

            <!-- Revenue -->
            <section class="card p-5">
                <h2 class="mb-1 text-h4 font-semibold text-ink">Revenue</h2>
                <div class="tabular mb-4 font-mono text-display-s text-ink">AED {{ money(revenue.total) }}</div>
                <div class="grid gap-1.5">
                    <div v-for="m in revenue.by_month" :key="m.month" class="flex items-center justify-between gap-3 border-b border-rule-cool pb-1.5 text-body-s last:border-0">
                        <span class="tabular font-mono text-ink-70">{{ m.month }}</span>
                        <span class="tabular font-mono text-ink">AED {{ money(m.total) }} <span class="text-slate">({{ m.count }})</span></span>
                    </div>
                    <p v-if="!revenue.by_month?.length" class="help">No payments recorded.</p>
                </div>
            </section>
        </div>

        <section class="mt-6">
            <h2 class="mb-3 text-h4 font-semibold text-ink">Pipeline</h2>
            <div class="grid gap-2">
                <div v-for="s in pipeline" :key="s.label" class="card flex items-center justify-between gap-4 p-3">
                    <StatusPill :tone="s.tone" :label="s.label" />
                    <span class="tabular font-mono text-body text-ink">{{ s.total }}</span>
                </div>
            </div>
        </section>
    </AdminLayout>
</template>
