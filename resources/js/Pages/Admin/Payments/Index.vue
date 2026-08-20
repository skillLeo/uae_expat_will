<script setup>
import { ref, watch } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { usePullToRefresh } from '@/Composables/usePullToRefresh';
import DataTable from '@/Components/DataTable.vue';
import StatusPill from '@/Components/StatusPill.vue';
import Sheet from '@/Components/Sheet.vue';
import FormField from '@/Components/FormField.vue';

const props = defineProps({
    payments: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    statuses: { type: Array, default: () => [] },
    totals: { type: Object, default: () => ({}) },
    types: { type: Array, default: () => [] },
});

const page = usePage();
const { distance, refreshing } = usePullToRefresh();
const can = (p) => (page.props.auth?.permissions ?? []).includes(p);

const status = ref(props.filters.status ?? '');
watch(status, () => router.get('/admin/payments', { status: status.value || undefined }, { preserveState: true, replace: true }));

const money = (n) => Number(n ?? 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

// ------------------------------------------------------------------ refunds
const refundSheet = ref(false);
const refundTarget = ref(null);
const refundPreview = ref(null);
const deduction = ref('');
const reason = ref('');

// A disbursement has no band. The refund is whatever the authority will
// actually return, which is a fact a person establishes, not a rule we apply.
const isDisbursement = () => refundPreview.value?.requires_documented_amount === true;
const canIssue = () => !!refundPreview.value
    && (!isDisbursement() || (deduction.value !== '' && reason.value.trim() !== ''));

async function openRefund(payment) {
    refundTarget.value = payment;
    refundPreview.value = null;
    refundSheet.value = true;

    // Show the band and the working BEFORE anything is committed.
    const response = await fetch(`/admin/payments/${payment.id}/refund-preview`);
    refundPreview.value = await response.json();
}

const confirmRefund = () => router.post(`/admin/payments/${refundTarget.value.id}/refund`, {
    documented_deduction: deduction.value === '' ? null : Number(deduction.value),
    reason: reason.value || null,
}, {
    preserveScroll: true,
    onSuccess: () => { refundSheet.value = false; deduction.value = ''; reason.value = ''; },
});

const checkStatus = (p) => router.post(`/admin/payments/${p.id}/check`, {}, { preserveScroll: true });

const columns = [
    { key: 'created_at', label: 'Created' },
    { key: 'reference', label: 'Case' },
    { key: 'type', label: 'For' },
    { key: 'stage_label', label: 'Detail' },
    { key: 'total_amount', label: 'Total', numeric: true },
    { key: 'status', label: 'Status' },
    { key: 'actions', label: '' },
];
</script>

<template>
    <AdminLayout title="Payments">
        <div
            v-if="distance > 0 || refreshing"
            class="mb-2 grid place-items-center text-caption text-slate"
            :style="{ height: `${Math.max(distance, refreshing ? 40 : 0)}px` }"
        >{{ refreshing ? 'Refreshing…' : 'Pull to refresh' }}</div>

        <!-- Professional fees are Summit's revenue. Authority charges are
             collected on somebody else's behalf. Showing one total for both
             would overstate what the business actually earned. -->
        <div class="mb-6 grid grid-cols-4 gap-4 max-[900px]:grid-cols-2 max-[600px]:grid-cols-1">
            <div v-for="[label, value, note] in [
                ['Professional fees paid', totals.paid, 'Summit\'s own fees'],
                ['Authority charges', totals.disbursements, 'collected for third parties'],
                ['Pending', totals.pending, 'awaiting payment'],
                ['Refunded', totals.refunded, 'returned to customers'],
            ]" :key="label" class="card p-4">
                <div class="eyebrow mb-2">{{ label }}</div>
                <div class="tabular font-mono text-h2 text-ink">AED {{ money(value) }}</div>
                <div class="mt-1 text-caption text-slate">{{ note }}</div>
            </div>
        </div>

        <div class="mb-4">
            <select v-model="status" class="field max-w-xs">
                <option value="">All statuses</option>
                <option v-for="s in statuses" :key="s.value" :value="s.value">{{ s.label }}</option>
            </select>
        </div>

        <DataTable :columns="columns" :rows="payments.data">
            <template #cell-created_at="{ row }"><span class="tabular font-mono text-caption">{{ new Date(row.created_at).toLocaleDateString('en-GB') }}</span></template>
            <template #cell-reference="{ row }">
                <Link :href="`/admin/cases/${row.case_id}`" class="tabular font-mono font-medium text-ink underline decoration-gold underline-offset-4">{{ row.reference }}</Link>
            </template>
            <template #cell-type="{ row }">
                <span class="pill" :class="row.type === 'disbursement' ? 'pill-attention' : 'pill-neutral'">{{ row.type_label }}</span>
            </template>
            <template #cell-total_amount="{ row }">{{ row.currency }} {{ money(row.total_amount) }}</template>
            <template #cell-status="{ row }"><StatusPill :tone="row.tone" :label="row.status_label" /></template>
            <template #cell-actions="{ row }">
                <div class="flex flex-wrap justify-end gap-1.5">
                    <button v-if="can('payments.view')" type="button" class="btn btn-sm btn-tertiary" @click="checkStatus(row)">Check</button>
                    <button v-if="can('payments.refund') && row.refundable" type="button" class="btn btn-sm btn-tertiary text-critical" @click="openRefund(row)">Refund</button>
                </div>
            </template>
        </DataTable>

        <div v-if="payments.links?.length > 3" class="mt-4 flex flex-wrap gap-1">
            <Link
                v-for="link in payments.links" :key="link.label" :href="link.url ?? '#'"
                class="tap grid place-items-center rounded-sm border px-3 text-body-s"
                :class="link.active ? 'border-ink bg-ink text-paper' : 'border-rule-cool text-ink-70'"
                v-html="link.label"
            />
        </div>

        <Sheet :open="refundSheet" title="Issue a refund" @close="refundSheet = false">
            <p v-if="!refundPreview" class="help">Calculating the band…</p>
            <div v-else>
                <div class="mb-4 rounded-md border p-4" :class="isDisbursement() ? 'border-critical-border bg-critical-bg' : 'border-attention-border bg-attention-bg'">
                    <div class="eyebrow mb-1.5">{{ refundPreview.band_label }}</div>
                    <p class="text-body-s text-ink">{{ refundPreview.band_description }}</p>
                </div>

                <template v-if="isDisbursement()">
                    <p class="mb-4 text-body-s leading-[1.6] text-ink">{{ refundPreview.reason }}</p>

                    <FormField id="r-amount" label="Amount the authority will return" required
                               help="Often nil once a matter has been lodged. Capped at the amount paid.">
                        <input id="r-amount" v-model="deduction" type="number" step="0.01" class="field" inputmode="decimal">
                    </FormField>
                    <FormField id="r-why" label="Why that amount is recoverable" required
                               help="Stored with the refund, so the figure can still be explained a year from now.">
                        <textarea id="r-why" v-model="reason" class="field min-h-[70px]" placeholder="Registration was never lodged; the Wills Service Centre confirmed the fee is returnable in full."></textarea>
                    </FormField>

                    <p v-if="!canIssue()" class="help">Both are required — a disbursement is never refunded by rule.</p>
                </template>

                <template v-else>
                    <dl class="mb-4 grid gap-2 text-body-s">
                        <div class="flex justify-between gap-3"><dt class="text-slate">Total paid</dt><dd class="tabular font-mono">{{ money(refundPreview.calculation.total_paid) }}</dd></div>
                        <div class="flex justify-between gap-3"><dt class="text-slate">Deduction</dt><dd class="tabular font-mono">{{ money(refundPreview.deduction) }}</dd></div>
                        <div class="flex justify-between gap-3 border-t border-rule-cool pt-2"><dt class="font-semibold text-ink">Refundable</dt><dd class="tabular font-mono font-medium">{{ money(refundPreview.refundable) }}</dd></div>
                    </dl>

                    <div class="mb-4">
                        <div class="eyebrow mb-2">Stages reached</div>
                        <div class="grid gap-1">
                            <div v-for="s in refundPreview.calculation.stages_reached" :key="s.stage" class="flex justify-between gap-3 text-caption">
                                <span class="text-ink">{{ s.label }}</span>
                                <span class="tabular font-mono text-slate">{{ new Date(s.occurred_at).toLocaleDateString('en-GB') }}</span>
                            </div>
                            <p v-if="!refundPreview.calculation.stages_reached.length" class="help">None recorded — nothing substantive had started.</p>
                        </div>
                    </div>

                    <FormField id="r-deduction" label="Documented deduction" help="Leave blank to use the fee allocation. Only for band B.">
                        <input id="r-deduction" v-model="deduction" type="number" step="0.01" class="field" inputmode="decimal">
                    </FormField>
                    <FormField id="r-reason" label="Reason recorded on the refund">
                        <textarea id="r-reason" v-model="reason" class="field min-h-[70px]" :placeholder="refundPreview.reason"></textarea>
                    </FormField>
                </template>

                <p class="help">The full working is stored so this figure can be explained months from now.</p>
            </div>

            <template #actions>
                <button type="button" class="btn btn-destructive" :disabled="!canIssue()" @click="confirmRefund">Issue refund</button>
                <button type="button" class="btn btn-tertiary" @click="refundSheet = false">Cancel</button>
            </template>
        </Sheet>
    </AdminLayout>
</template>
