<script setup>
/**
 * Blog administration.
 *
 * Summit writes the articles. This screen stores them, dates them and puts the
 * author's name on them. The two fields that matter most for how a legal
 * article is judged — a named author and a review date — are required rather
 * than optional, because an article missing either is worth very little.
 */
import { ref, reactive, computed } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import DataTable from '@/Components/DataTable.vue';
import StatusPill from '@/Components/StatusPill.vue';
import Sheet from '@/Components/Sheet.vue';
import FormField from '@/Components/FormField.vue';

const props = defineProps({
    posts: { type: Array, default: () => [] },
    categories: { type: Array, default: () => [] },
});

const page = usePage();
const can = (p) => (page.props.auth?.permissions ?? []).includes(p);
const errors = computed(() => page.props.errors ?? {});

const sheet = ref(false);
const editing = ref(null);

const blank = {
    title: '', slug: '', category: '', excerpt: '', body: '',
    author_name: '', author_title: '', seo_title: '', meta_description: '',
    is_published: false,
};
const form = reactive({ ...blank });

function open(post = null) {
    editing.value = post;
    Object.assign(form, blank, post ?? {});
    sheet.value = true;
}

function save() {
    const done = { preserveScroll: true, onSuccess: () => { sheet.value = false; } };

    editing.value
        ? router.patch(`/admin/content/posts/${editing.value.id}`, form, done)
        : router.post('/admin/content/posts', form, done);
}

const markReviewed = (p) => router.post(`/admin/content/posts/${p.id}/reviewed`, {}, { preserveScroll: true });

const remove = (p) => confirm(`Delete "${p.title}"? It disappears from the site immediately.`)
    && router.delete(`/admin/content/posts/${p.id}`, { preserveScroll: true });

const when = (iso) => iso ? new Date(iso).toLocaleDateString('en-GB') : '—';

const columns = [
    { key: 'title', label: 'Article' },
    { key: 'author_name', label: 'Author' },
    { key: 'reviewed', label: 'Last checked' },
    { key: 'status', label: 'Status' },
    { key: 'actions', label: '' },
];
</script>

<template>
    <AdminLayout title="Insights">
        <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-h2 font-semibold text-ink">Insights</h1>
                <p class="help mt-1">
                    Articles are public the moment they are published, and appear in the sitemap.
                </p>
            </div>
            <button v-if="can('content.edit')" type="button" class="btn btn-primary" @click="open()">
                Write an article
            </button>
        </div>

        <DataTable :columns="columns" :rows="posts">
            <template #cell-title="{ row }">
                <div class="font-medium text-ink">{{ row.title }}</div>
                <div class="mt-0.5 font-mono text-micro text-slate">
                    /blog/{{ row.slug }} · {{ row.reading_minutes }} min
                    <span v-if="row.category"> · {{ row.category }}</span>
                </div>
            </template>

            <template #cell-reviewed="{ row }">
                <span class="tabular font-mono text-caption">{{ when(row.reviewed_at ?? row.published_at) }}</span>
                <span v-if="!row.reviewed_at && row.is_published" class="help block">never re-checked</span>
            </template>

            <template #cell-status="{ row }">
                <StatusPill
                    :tone="row.is_published ? 'positive' : 'neutral'"
                    :label="row.is_published ? 'Live' : 'Draft'"
                />
            </template>

            <template #cell-actions="{ row }">
                <div class="flex flex-wrap justify-end gap-1.5">
                    <a v-if="row.is_published" :href="row.url" target="_blank" rel="noopener" class="btn btn-sm btn-tertiary">View</a>
                    <button v-if="can('content.edit')" type="button" class="btn btn-sm btn-tertiary" @click="open(row)">Edit</button>
                    <button
                        v-if="can('content.edit') && row.is_published" type="button"
                        class="btn btn-sm btn-tertiary" title="Records that you have read it again and it still stands"
                        @click="markReviewed(row)"
                    >Mark checked</button>
                    <button v-if="can('content.edit')" type="button" class="btn btn-sm btn-tertiary text-critical" @click="remove(row)">Delete</button>
                </div>
            </template>
        </DataTable>

        <p v-if="!posts.length" class="help mt-4">
            Nothing written yet. The blog is not linked from the site until the first article is published.
        </p>

        <Sheet :open="sheet" :title="editing ? 'Edit article' : 'Write an article'" size="lg" @close="sheet = false">
            <div class="grid gap-4">
                <FormField id="p-title" label="Title" required :error="errors.title">
                    <input id="p-title" v-model="form.title" class="field">
                </FormField>

                <FormField
                    id="p-slug" label="Web address" :error="errors.slug"
                    help="Leave blank and it is made from the title. Changing it on a live article breaks existing links."
                >
                    <input id="p-slug" v-model="form.slug" class="field" placeholder="do-you-need-a-uae-will">
                </FormField>

                <FormField id="p-cat" label="Category" :error="errors.category" help="Groups related reading at the foot of an article.">
                    <input id="p-cat" v-model="form.category" class="field" list="post-categories">
                    <datalist id="post-categories">
                        <option v-for="c in categories" :key="c" :value="c"></option>
                    </datalist>
                </FormField>

                <FormField
                    id="p-excerpt" label="Summary" required :error="errors.excerpt"
                    help="Shown on the index, and used as the search description when none is set."
                >
                    <textarea id="p-excerpt" v-model="form.excerpt" class="field min-h-[80px]"></textarea>
                </FormField>

                <FormField
                    id="p-body" label="Article" required :error="errors.body"
                    help="Ordinary HTML. Headings, lists, links and tables are styled to match the site."
                >
                    <textarea id="p-body" v-model="form.body" class="field min-h-[280px] font-mono text-caption"></textarea>
                </FormField>

                <div class="grid grid-cols-2 gap-4 max-[719px]:grid-cols-1">
                    <FormField id="p-author" label="Author" required :error="errors.author_name">
                        <input id="p-author" v-model="form.author_name" class="field">
                    </FormField>
                    <FormField id="p-role" label="Author title" required :error="errors.author_title">
                        <input id="p-role" v-model="form.author_title" class="field" placeholder="Principal Legal Consultant">
                    </FormField>
                </div>

                <FormField id="p-seo" label="Search title" :error="errors.seo_title" help="Defaults to the article title.">
                    <input id="p-seo" v-model="form.seo_title" class="field">
                </FormField>

                <FormField id="p-meta" label="Search description" :error="errors.meta_description" help="Defaults to the summary above.">
                    <textarea id="p-meta" v-model="form.meta_description" class="field min-h-[60px]"></textarea>
                </FormField>

                <label class="flex items-center gap-2.5">
                    <input v-model="form.is_published" type="checkbox" class="tap accent-gold">
                    <span class="text-body-s">Publish this article</span>
                </label>
            </div>

            <template #actions>
                <button type="button" class="btn btn-primary" @click="save">
                    {{ editing ? 'Save' : 'Create' }}
                </button>
                <button type="button" class="btn btn-tertiary" @click="sheet = false">Cancel</button>
            </template>
        </Sheet>
    </AdminLayout>
</template>
