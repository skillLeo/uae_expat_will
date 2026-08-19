<script setup>
import { ref, watch } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import DataTable from '@/Components/DataTable.vue';
import StatusPill from '@/Components/StatusPill.vue';

const props = defineProps({
    cases: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    statuses: { type: Array, default: () => [] },
});

const q = ref(props.filters.q ?? '');
const status = ref(props.filters.status ?? '');

let timer;
watch([q, status], () => {
    clearTimeout(timer);
    timer = setTimeout(() => {
        router.get('/admin/cases', { q: q.value || undefined, status: status.value || undefined }, {
            preserveState: true, replace: true,
        });
    }, 250);
});

const columns = [
    { key: 'reference', label: 'Reference' },
    { key: 'customer', label: 'Customer' },
    { key: 'status', label: 'Status' },
    { key: 'assignee', label: 'Assigned to' },
    { key: 'created_at', label: 'Created' },
];
</script>

<template>
    <AdminLayout title="Cases">
        <div class="mb-4 flex flex-wrap gap-3">
            <input v-model="q" type="search" class="field max-w-xs" placeholder="Search reference, name or email" inputmode="search">
            <select v-model="status" class="field max-w-xs">
                <option value="">All statuses</option>
                <option v-for="s in statuses" :key="s.value" :value="s.value">{{ s.label }}</option>
            </select>
        </div>

        <DataTable :columns="columns" :rows="cases.data">
            <template #cell-reference="{ row }">
                <Link :href="`/admin/cases/${row.id}`" class="tabular font-mono font-medium text-ink underline decoration-gold underline-offset-4">
                    {{ row.reference }}
                </Link>
            </template>
            <template #cell-customer="{ row }">
                <span :class="row.is_restricted ? 'text-slate italic' : ''">{{ row.customer?.name ?? '—' }}</span>
            </template>
            <template #cell-status="{ row }">
                <div class="flex flex-wrap items-center gap-1.5">
                    <StatusPill :tone="row.tone" :label="row.status_label" />
                    <StatusPill v-if="row.is_restricted" tone="held" label="Restricted" />
                </div>
            </template>
            <template #cell-assignee="{ row }">{{ row.assignee ?? 'Unassigned' }}</template>
            <template #cell-created_at="{ row }">{{ new Date(row.created_at).toLocaleDateString('en-GB') }}</template>
        </DataTable>

        <div v-if="cases.links?.length > 3" class="mt-4 flex flex-wrap gap-1">
            <Link
                v-for="link in cases.links" :key="link.label"
                :href="link.url ?? '#'"
                class="tap grid place-items-center rounded-sm border px-3 text-body-s"
                :class="link.active ? 'border-ink bg-ink text-paper' : 'border-rule-cool text-ink-70'"
                v-html="link.label"
            />
        </div>
    </AdminLayout>
</template>
