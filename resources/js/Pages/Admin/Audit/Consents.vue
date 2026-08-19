<script setup>
import { usePage } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import DataTable from '@/Components/DataTable.vue';
import StatusPill from '@/Components/StatusPill.vue';

defineProps({
    consents: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    types: { type: Array, default: () => [] },
});

const page = usePage();
const can = (p) => (page.props.auth?.permissions ?? []).includes(p);

const columns = [
    { key: 'accepted_at', label: 'When' },
    { key: 'type', label: 'Type' },
    { key: 'version', label: 'Version' },
    { key: 'wording_hash', label: 'Wording hash' },
    { key: 'reference', label: 'Reference' },
    { key: 'accepted', label: 'Accepted' },
];
</script>

<template>
    <AdminLayout title="Consent records">
        <template #action>
            <a v-if="can('consents.export')" href="/admin/consents/export" class="btn btn-sm btn-secondary">Export CSV</a>
        </template>

        <div class="card-paper mb-4 border border-rule-warm p-4">
            <p class="text-body-s text-ink">
                The wording hash is what makes each record evidential: it proves exactly which text was on
                screen, not merely that a box was ticked.
            </p>
        </div>

        <DataTable :columns="columns" :rows="consents.data">
            <template #cell-accepted_at="{ row }"><span class="tabular font-mono text-caption">{{ new Date(row.accepted_at).toLocaleString('en-GB') }}</span></template>
            <template #cell-type="{ row }"><span class="pill pill-neutral">{{ row.type }}</span></template>
            <template #cell-wording_hash="{ row }"><code class="font-mono text-micro text-slate">{{ row.wording_hash }}</code></template>
            <template #cell-accepted="{ row }"><StatusPill :tone="row.accepted ? 'positive' : 'neutral'" :label="row.accepted ? 'Yes' : 'No'" /></template>
        </DataTable>
    </AdminLayout>
</template>
