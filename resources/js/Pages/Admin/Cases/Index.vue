<script setup>
import { ref, watch, computed } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { usePullToRefresh } from '@/Composables/usePullToRefresh';
import DataTable from '@/Components/DataTable.vue';
import StatusPill from '@/Components/StatusPill.vue';
import Sheet from '@/Components/Sheet.vue';
import FormField from '@/Components/FormField.vue';

const props = defineProps({
    cases: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    statuses: { type: Array, default: () => [] },
    savedViews: { type: Array, default: () => [] },
});

const q = ref(props.filters.q ?? '');
const status = ref(props.filters.status ?? '');
const overdue = ref(!!props.filters.overdue);

let timer;
watch([q, status, overdue], () => {
    clearTimeout(timer);
    timer = setTimeout(() => {
        router.get('/admin/cases', {
            q: q.value || undefined,
            status: status.value || undefined,
            overdue: overdue.value || undefined,
        }, { preserveState: true, replace: true });
    }, 250);
});

// ------------------------------------------------------------- saved views
const saveSheet = ref(false);
const viewName = ref('');
const shareView = ref(false);

const currentFilters = computed(() => ({
    q: q.value || undefined,
    status: status.value || undefined,
    overdue: overdue.value || undefined,
}));

function applyView(view) {
    q.value = view.filters.q ?? '';
    status.value = view.filters.status ?? '';
    overdue.value = !!view.filters.overdue;
}

const saveView = () => router.post('/admin/saved-views', {
    name: viewName.value,
    resource: 'cases',
    filters: currentFilters.value,
    is_shared: shareView.value,
}, {
    preserveScroll: true,
    onSuccess: () => { saveSheet.value = false; viewName.value = ''; shareView.value = false; },
});

const deleteView = (v) => confirm(`Remove the view "${v.name}"?`)
    && router.delete(`/admin/saved-views/${v.id}`, { preserveScroll: true });

const columns = [
    { key: 'reference', label: 'Reference' },
    { key: 'customer', label: 'Customer' },
    { key: 'status', label: 'Status' },
    { key: 'assignee', label: 'Assigned to' },
    { key: 'created_at', label: 'Created' },
];

const { distance, refreshing } = usePullToRefresh();

const page = usePage();
const can = (p) => (page.props.auth?.permissions ?? []).includes(p);
</script>

<template>
    <AdminLayout title="Cases">
        <div
            v-if="distance > 0 || refreshing"
            class="mb-2 grid place-items-center text-caption text-slate"
            :style="{ height: `${Math.max(distance, refreshing ? 40 : 0)}px` }"
        >{{ refreshing ? 'Refreshing…' : 'Pull to refresh' }}</div>

        <div v-if="savedViews.length" class="mb-3 flex flex-wrap items-center gap-2">
            <span class="eyebrow">Saved views</span>
            <span v-for="v in savedViews" :key="v.id" class="inline-flex items-center gap-1.5 rounded-sm border border-rule-cool">
                <button type="button" class="tap px-3 text-body-s text-ink-70 hover:text-ink" @click="applyView(v)">
                    {{ v.name }}
                    <span v-if="v.is_shared" class="ml-1 font-mono text-micro text-slate">shared</span>
                </button>
                <button
                    v-if="v.is_mine" type="button" class="px-1.5 text-caption text-slate hover:text-critical"
                    :aria-label="`Remove ${v.name}`" @click="deleteView(v)"
                >×</button>
            </span>
        </div>

        <div class="mb-4 flex flex-wrap items-center gap-3">
            <input v-model="q" type="search" class="field max-w-xs" placeholder="Search reference, name or email" inputmode="search">
            <select v-model="status" class="field max-w-xs">
                <option value="">All statuses</option>
                <option v-for="s in statuses" :key="s.value" :value="s.value">{{ s.label }}</option>
            </select>
            <label class="tap flex items-center gap-2 text-body-s">
                <input v-model="overdue" type="checkbox" class="accent-gold">
                Overdue only
            </label>
            <button type="button" class="btn btn-sm btn-tertiary" @click="saveSheet = true">Save this view</button>
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

        <Sheet :open="saveSheet" title="Save this view" size="sm" @close="saveSheet = false">
            <p class="help mb-4">Saves the search, status and overdue filters currently applied.</p>
            <FormField id="v-name" label="Name" required>
                <input id="v-name" v-model="viewName" class="field" placeholder="Overdue and unassigned">
            </FormField>
            <label v-if="can('cases.view.all')" class="flex items-start gap-2.5">
                <input v-model="shareView" type="checkbox" class="tap mt-0.5 accent-gold">
                <span class="text-body-s">Share with the team
                    <span class="help block">Everyone who can see all cases gets this view too.</span>
                </span>
            </label>
            <template #actions>
                <button type="button" class="btn btn-primary" :disabled="!viewName.trim()" @click="saveView">Save view</button>
                <button type="button" class="btn btn-tertiary" @click="saveSheet = false">Cancel</button>
            </template>
        </Sheet>

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
