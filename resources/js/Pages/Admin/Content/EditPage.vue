<script setup>
/**
 * Page editor.
 *
 * Sections hold structured data (lists, cards, route tables), so the editor
 * renders a field per known key and falls back to raw JSON for anything it does
 * not have a bespoke control for — which keeps every section editable rather
 * than only the simple ones.
 */
import { ref, reactive, computed } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import FormField from '@/Components/FormField.vue';
import SortableList from '@/Components/SortableList.vue';

const props = defineProps({
    page: { type: Object, required: true },
    sections: { type: Array, default: () => [] },
});

const meta = useForm({
    title: props.page.title,
    seo_title: props.page.seo_title,
    meta_description: props.page.meta_description,
    breadcrumb: props.page.breadcrumb,
});

const drafts = reactive(
    Object.fromEntries(props.sections.map((s) => [s.id, {
        heading: s.heading ?? '',
        subheading: s.subheading ?? '',
        body: s.body ?? '',
        items: s.items ? JSON.stringify(s.items, null, 2) : '',
        settings: s.settings ? JSON.stringify(s.settings, null, 2) : '',
    }])),
);

const errors = reactive({});
const saving = ref(null);

function saveSection(section) {
    const draft = drafts[section.id];
    const payload = { heading: draft.heading, subheading: draft.subheading, body: draft.body };

    // Structured fields are edited as JSON. Invalid JSON is refused here rather
    // than silently blanking a section's content.
    for (const key of ['items', 'settings']) {
        if (!draft[key].trim()) { payload[key] = null; continue; }
        try {
            payload[key] = JSON.parse(draft[key]);
        } catch {
            errors[section.id] = `The ${key} field is not valid JSON. Nothing was saved.`;
            return;
        }
    }

    delete errors[section.id];
    saving.value = section.id;
    router.patch(`/admin/content/sections/${section.id}`, payload, {
        preserveScroll: true,
        onFinish: () => { saving.value = null; },
    });
}

const reorder = (order) => router.post(`/admin/content/${props.page.id}/reorder`, { order }, { preserveScroll: true });
</script>

<template>
    <AdminLayout :title="page.title" back-href="/admin/content">
        <template #action>
            <a :href="page.slug" target="_blank" rel="noopener" class="btn btn-sm btn-secondary">Preview page</a>
        </template>

        <section class="card mb-6 p-5">
            <h2 class="mb-4 text-h4 font-semibold text-ink">Page details</h2>
            <div class="grid grid-cols-2 gap-4 max-[719px]:grid-cols-1">
                <FormField id="p-title" label="Title"><input id="p-title" v-model="meta.title" class="field"></FormField>
                <FormField id="p-crumb" label="Breadcrumb"><input id="p-crumb" v-model="meta.breadcrumb" class="field"></FormField>
            </div>
            <FormField id="p-seo" label="SEO title" help="Shown in the browser tab and search results.">
                <input id="p-seo" v-model="meta.seo_title" class="field">
            </FormField>
            <FormField id="p-desc" label="Meta description" help="Around 155 characters.">
                <textarea id="p-desc" v-model="meta.meta_description" class="field min-h-[70px]"></textarea>
            </FormField>
            <p class="help mb-4">
                The URL is fixed in code and cannot be changed here. A moved URL is an SEO incident,
                not a content change.
            </p>
            <button type="button" class="btn btn-primary" :disabled="meta.processing" @click="meta.patch(`/admin/content/${page.id}`, { preserveScroll: true })">
                {{ meta.processing ? 'Saving…' : 'Save details' }}
            </button>
        </section>

        <h2 class="mb-3 text-h4 font-semibold text-ink">Sections</h2>
        <SortableList :items="sections" @reorder="reorder">
            <template #default="{ item: s }">
                <details class="group">
                    <summary class="flex cursor-pointer flex-wrap items-center gap-2">
                        <code class="font-mono text-caption text-gold-strong">{{ s.key }}</code>
                        <span class="pill pill-neutral">{{ s.type }}</span>
                        <span class="truncate text-body-s text-ink">{{ s.heading || s.subheading || '—' }}</span>
                    </summary>

                    <div class="mt-4 border-t border-rule-cool pt-4">
                        <FormField :id="`s-h-${s.id}`" label="Heading"><textarea :id="`s-h-${s.id}`" v-model="drafts[s.id].heading" class="field min-h-[50px]"></textarea></FormField>
                        <FormField :id="`s-sh-${s.id}`" label="Subheading"><textarea :id="`s-sh-${s.id}`" v-model="drafts[s.id].subheading" class="field min-h-[60px]"></textarea></FormField>
                        <FormField :id="`s-b-${s.id}`" label="Body"><textarea :id="`s-b-${s.id}`" v-model="drafts[s.id].body" class="field min-h-[120px]"></textarea></FormField>
                        <FormField :id="`s-i-${s.id}`" label="Items" help="Structured content as JSON — cards, list rows, table rows.">
                            <textarea :id="`s-i-${s.id}`" v-model="drafts[s.id].items" class="field min-h-[160px] font-mono text-body-s"></textarea>
                        </FormField>
                        <FormField :id="`s-s-${s.id}`" label="Settings" help="Links, notes and layout flags as JSON.">
                            <textarea :id="`s-s-${s.id}`" v-model="drafts[s.id].settings" class="field min-h-[120px] font-mono text-body-s"></textarea>
                        </FormField>

                        <p v-if="errors[s.id]" class="error mb-3" role="alert">{{ errors[s.id] }}</p>

                        <button type="button" class="btn btn-sm btn-primary" :disabled="saving === s.id" @click="saveSection(s)">
                            {{ saving === s.id ? 'Saving…' : 'Save section' }}
                        </button>
                    </div>
                </details>
            </template>
        </SortableList>
    </AdminLayout>
</template>
