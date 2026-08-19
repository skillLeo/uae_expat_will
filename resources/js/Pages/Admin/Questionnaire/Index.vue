<script setup>
import { computed } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import StatusPill from '@/Components/StatusPill.vue';

defineProps({
    questionnaire: { type: Object, default: null },
    versions: { type: Array, default: () => [] },
});

const page = usePage();
const can = (p) => (page.props.auth?.permissions ?? []).includes(p);

const tone = (status) => ({ published: 'positive', draft: 'attention', archived: 'neutral' }[status] ?? 'neutral');

const createDraft = () => router.post('/admin/questionnaire/draft');
const publish = (v) => {
    if (confirm(`Publish version ${v.version_number}? This becomes live immediately for new assessments.`)) {
        router.post(`/admin/questionnaire/${v.id}/publish`);
    }
};
const rollback = (v) => {
    if (confirm(`Roll back to version ${v.version_number}? The current live version will be archived.`)) {
        router.post(`/admin/questionnaire/${v.id}/rollback`);
    }
};
</script>

<template>
    <AdminLayout title="Questionnaire">
        <template #action>
            <button v-if="can('questionnaire.edit')" type="button" class="btn btn-sm btn-primary" @click="createDraft">
                New draft
            </button>
        </template>

        <div class="card-paper mb-6 border border-rule-warm p-5">
            <h2 class="mb-1.5 text-h4 font-semibold text-ink">{{ questionnaire?.name }}</h2>
            <p class="prose-measure text-body-s leading-[1.6] text-ink-70">{{ questionnaire?.description }}</p>
            <p class="help mt-3">
                A published version is immutable. To change anything, create a draft, edit it, preview it,
                then publish. An assessment already in progress keeps the version it started on.
            </p>
        </div>

        <div class="grid gap-2">
            <article v-for="v in versions" :key="v.id" class="card p-5">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div class="min-w-0">
                        <div class="mb-2 flex flex-wrap items-center gap-2">
                            <span class="tabular font-mono text-body font-medium text-ink">Version {{ v.version_number }}</span>
                            <StatusPill :tone="tone(v.status)" :label="v.status" />
                        </div>
                        <p v-if="v.notes" class="prose-measure mb-2 text-body-s text-ink-70">{{ v.notes }}</p>
                        <dl class="tabular flex flex-wrap gap-x-6 gap-y-1 font-mono text-caption text-slate">
                            <div><dt class="inline">questions</dt> <dd class="inline text-ink">{{ v.questions }}</dd></div>
                            <div><dt class="inline">rules</dt> <dd class="inline text-ink">{{ v.rules }}</dd></div>
                            <div><dt class="inline">assessments</dt> <dd class="inline text-ink">{{ v.assessments }}</dd></div>
                        </dl>
                        <p v-if="v.published_at" class="help mt-2">
                            Published {{ new Date(v.published_at).toLocaleString('en-GB') }}<span v-if="v.published_by"> by {{ v.published_by }}</span>
                        </p>
                    </div>

                    <div class="flex flex-none flex-wrap gap-2">
                        <Link :href="`/admin/questionnaire/${v.id}`" class="btn btn-sm btn-secondary">
                            {{ v.status === 'draft' ? 'Edit' : 'View' }}
                        </Link>
                        <button
                            v-if="v.status === 'draft' && can('questionnaire.publish')"
                            type="button" class="btn btn-sm btn-primary" @click="publish(v)"
                        >Publish</button>
                        <button
                            v-if="v.status === 'archived' && can('questionnaire.rollback')"
                            type="button" class="btn btn-sm btn-secondary" @click="rollback(v)"
                        >Roll back to this</button>
                    </div>
                </div>
            </article>
        </div>
    </AdminLayout>
</template>
