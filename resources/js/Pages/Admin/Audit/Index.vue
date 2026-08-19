<script setup>
/**
 * The audit log viewer.
 *
 * READ ONLY, deliberately. There is no edit or delete control anywhere on this
 * screen — the table is append-only at the database level, and offering an
 * affordance that would fail is worse than not offering one.
 */
import { ref, watch } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import DataTable from '@/Components/DataTable.vue';
import { usePullToRefresh } from '@/Composables/usePullToRefresh';

const props = defineProps({
    entries: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    logs: { type: Array, default: () => [] },
});

const page = usePage();
const can = (p) => (page.props.auth?.permissions ?? []).includes(p);
const { distance, refreshing } = usePullToRefresh();

const q = ref(props.filters.q ?? '');
const log = ref(props.filters.log ?? '');
const expanded = ref(null);

let timer;
watch([q, log], () => {
    clearTimeout(timer);
    timer = setTimeout(() => {
        router.get('/admin/audit', { q: q.value || undefined, log: log.value || undefined }, {
            preserveState: true, replace: true,
        });
    }, 250);
});

const columns = [
    { key: 'created_at', label: 'When' },
    { key: 'log_name', label: 'Area' },
    { key: 'description', label: 'What happened' },
    { key: 'causer', label: 'Who' },
    { key: 'ip_address', label: 'IP' },
];
</script>

<template>
    <AdminLayout title="Audit log">
        <template #action>
            <a v-if="can('audit.export')" href="/admin/audit/export" class="btn btn-sm btn-secondary">Export CSV</a>
        </template>

        <div
            v-if="distance > 0 || refreshing"
            class="mb-2 grid place-items-center text-caption text-slate"
            :style="{ height: `${Math.max(distance, refreshing ? 40 : 0)}px` }"
        >{{ refreshing ? 'Refreshing…' : 'Pull to refresh' }}</div>

        <div class="card-paper mb-4 border border-rule-warm p-4">
            <p class="text-body-s text-ink">
                This log cannot be edited or deleted — not from here, and not from the database. Entries are
                appended only.
            </p>
        </div>

        <div class="mb-4 flex flex-wrap gap-3">
            <input v-model="q" type="search" class="field max-w-xs" placeholder="Search descriptions" inputmode="search">
            <select v-model="log" class="field max-w-xs">
                <option value="">All areas</option>
                <option v-for="l in logs" :key="l" :value="l">{{ l }}</option>
            </select>
        </div>

        <DataTable :columns="columns" :rows="entries.data">
            <template #cell-created_at="{ row }">
                <span class="tabular font-mono text-caption">{{ new Date(row.created_at).toLocaleString('en-GB') }}</span>
            </template>
            <template #cell-log_name="{ row }"><span class="pill pill-neutral">{{ row.log_name }}</span></template>
            <template #cell-description="{ row }">
                <button type="button" class="text-left text-ink hover:underline" @click="expanded = expanded === row.id ? null : row.id">
                    {{ row.description }}
                    <span v-if="row.subject" class="block font-mono text-micro text-slate">{{ row.subject }}</span>
                </button>
                <pre v-if="expanded === row.id" class="scroll-x mt-2 rounded-xs bg-paper p-2 font-mono text-micro text-ink-70">{{ JSON.stringify(row.properties, null, 2) }}</pre>
            </template>
            <template #cell-ip_address="{ row }"><span class="tabular font-mono text-caption text-slate">{{ row.ip_address ?? '—' }}</span></template>
        </DataTable>

        <div v-if="entries.links?.length > 3" class="mt-4 flex flex-wrap gap-1">
            <a
                v-for="link in entries.links" :key="link.label" :href="link.url ?? undefined"
                class="tap grid place-items-center rounded-sm border px-3 text-body-s"
                :class="link.active ? 'border-ink bg-ink text-paper' : 'border-rule-cool text-ink-70'"
                v-html="link.label"
            />
        </div>
    </AdminLayout>
</template>
