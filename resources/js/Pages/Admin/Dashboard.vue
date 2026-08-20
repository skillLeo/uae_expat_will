<script setup>
import { Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import StatusPill from '@/Components/StatusPill.vue';
import SystemHealthPanel from '@/Components/SystemHealthPanel.vue';

defineProps({
    stats: { type: Object, required: true },
    health: { type: Object, default: null },
    pipeline: { type: Array, default: () => [] },
    recent: { type: Array, default: () => [] },
});
</script>

<template>
    <AdminLayout title="Dashboard">
        <!-- Only rendered for whoever can act on it. -->
        <SystemHealthPanel v-if="health" :health="health" />

        <div class="grid grid-cols-4 gap-4 max-[900px]:grid-cols-2">
            <div v-for="(value, key) in stats" :key="key" class="card p-4">
                <div class="eyebrow mb-2">{{ String(key).replace(/_/g, ' ') }}</div>
                <div class="tabular font-mono text-display-s text-ink">{{ value }}</div>
            </div>
        </div>

        <section class="mt-8">
            <h2 class="mb-4 text-h3 font-semibold text-ink">Pipeline</h2>
            <div class="grid gap-2">
                <div v-for="stage in pipeline" :key="stage.key" class="card flex items-center justify-between gap-4 p-4">
                    <StatusPill :tone="stage.tone" :label="stage.label" />
                    <span class="tabular font-mono text-h4 text-ink">{{ stage.count }}</span>
                </div>
            </div>
        </section>

        <section class="mt-8">
            <div class="mb-4 flex items-baseline justify-between gap-4">
                <h2 class="text-h3 font-semibold text-ink">Recent cases</h2>
                <Link href="/admin/cases" class="text-body-s font-medium text-gold-strong">All cases →</Link>
            </div>
            <div class="grid gap-2">
                <Link
                    v-for="row in recent" :key="row.id" :href="`/admin/cases/${row.id}`"
                    class="card flex flex-wrap items-center justify-between gap-3 p-4 hover:border-gold"
                >
                    <div class="min-w-0">
                        <div class="tabular font-mono text-body-s text-ink">{{ row.reference }}</div>
                        <div class="truncate text-caption text-slate">{{ row.customer }}</div>
                    </div>
                    <div class="flex flex-none items-center gap-2">
                        <StatusPill v-if="row.is_restricted" tone="held" label="Restricted" />
                        <StatusPill :tone="row.tone" :label="row.status_label" />
                    </div>
                </Link>
                <p v-if="!recent.length" class="card p-6 text-center text-body-s text-slate">No cases yet.</p>
            </div>
        </section>
    </AdminLayout>
</template>
