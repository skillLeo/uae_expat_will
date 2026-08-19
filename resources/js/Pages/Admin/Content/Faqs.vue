<script setup>
import { ref, reactive, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Sheet from '@/Components/Sheet.vue';
import FormField from '@/Components/FormField.vue';
import SortableList from '@/Components/SortableList.vue';

const props = defineProps({
    categories: { type: Array, default: () => [] },
    faqs: { type: Array, default: () => [] },
});

const active = ref(props.categories[0]?.key ?? '');
const sheet = ref(false);
const editing = ref(null);
const form = reactive({ category_key: '', question: '', answer: '', is_published: true });

const inCategory = computed(() => props.faqs.filter((f) => f.category_key === active.value));

function open(faq = null) {
    editing.value = faq;
    Object.assign(form, faq ?? { category_key: active.value, question: '', answer: '', is_published: true });
    sheet.value = true;
}

function save() {
    const done = { onSuccess: () => { sheet.value = false; }, preserveScroll: true };
    editing.value
        ? router.patch(`/admin/content/faqs/${editing.value.id}`, { ...form }, done)
        : router.post('/admin/content/faqs', { ...form }, done);
}

const remove = (f) => confirm('Delete this question?') && router.delete(`/admin/content/faqs/${f.id}`, { preserveScroll: true });
const reorder = (order) => router.post('/admin/content/faqs/reorder', { order }, { preserveScroll: true });
const togglePublished = (f) => router.patch(`/admin/content/faqs/${f.id}`, { is_published: !f.is_published }, { preserveScroll: true });
</script>

<template>
    <AdminLayout title="FAQs" back-href="/admin/content">
        <template #action>
            <button type="button" class="btn btn-sm btn-primary" @click="open()">Add question</button>
        </template>

        <p class="help mb-4">
            {{ faqs.length }} questions. Every answer is rendered into the page even when collapsed,
            so all of it is findable and indexable.
        </p>

        <div class="mb-5 flex flex-wrap gap-2">
            <button
                v-for="c in categories" :key="c.key" type="button"
                class="tap rounded-sm border px-3 text-body-s"
                :class="active === c.key ? 'border-ink bg-ink text-paper' : 'border-rule-cool text-ink-70 hover:border-gold'"
                @click="active = c.key"
            >
                {{ c.label }}
                <span class="tabular font-mono text-caption opacity-70">{{ faqs.filter((f) => f.category_key === c.key).length }}</span>
            </button>
        </div>

        <SortableList :items="inCategory" @reorder="reorder">
            <template #default="{ item: f }">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="min-w-0 flex-1">
                        <p class="mb-1 text-body-s font-semibold text-ink">{{ f.question }}</p>
                        <p class="prose-measure line-clamp-2 text-caption leading-[1.6] text-ink-70">{{ f.answer }}</p>
                        <code class="mt-1 block font-mono text-micro text-slate">#{{ f.anchor }}</code>
                    </div>
                    <div class="flex flex-none gap-1.5">
                        <button type="button" class="btn btn-sm btn-tertiary" @click="open(f)">Edit</button>
                        <button type="button" class="btn btn-sm btn-tertiary" @click="togglePublished(f)">{{ f.is_published ? 'Hide' : 'Show' }}</button>
                        <button type="button" class="btn btn-sm btn-tertiary text-critical" @click="remove(f)">Delete</button>
                    </div>
                </div>
            </template>
        </SortableList>

        <Sheet :open="sheet" :title="editing ? 'Edit question' : 'Add question'" size="lg" @close="sheet = false">
            <FormField id="f-cat" label="Category" required>
                <select id="f-cat" v-model="form.category_key" class="field">
                    <option v-for="c in categories" :key="c.key" :value="c.key">{{ c.label }}</option>
                </select>
            </FormField>
            <FormField id="f-q" label="Question" required><textarea id="f-q" v-model="form.question" class="field min-h-[60px]"></textarea></FormField>
            <FormField id="f-a" label="Answer" required help="Plain text. Do not alter Summit's legal wording.">
                <textarea id="f-a" v-model="form.answer" class="field min-h-[200px]"></textarea>
            </FormField>
            <label class="flex items-center gap-2.5"><input v-model="form.is_published" type="checkbox" class="tap accent-gold"><span class="text-body-s">Published</span></label>
            <template #actions>
                <button type="button" class="btn btn-primary" @click="save">Save</button>
                <button type="button" class="btn btn-tertiary" @click="sheet = false">Cancel</button>
            </template>
        </Sheet>
    </AdminLayout>
</template>
