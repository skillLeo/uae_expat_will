<script setup>
/**
 * Document upload.
 *
 * On a phone the camera is offered directly via `capture`, because
 * photographing a passport is what people actually do and making them find the
 * file picker first is friction for no reason.
 *
 * Everything lands on the private disk and every link here is signed and
 * expires in minutes.
 */
import { ref, reactive } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import ClientLayout from '@/Layouts/ClientLayout.vue';
import StatusPill from '@/Components/StatusPill.vue';
import FormField from '@/Components/FormField.vue';

const props = defineProps({
    record: { type: Object, required: true },
    categories: { type: Array, default: () => [] },
    documents: { type: Array, default: () => [] },
    uploadEnabled: { type: Boolean, default: true },
});

const form = useForm({ category: props.categories[0]?.value ?? 'other', file: null });
const fileInput = ref(null);
const cameraInput = ref(null);

function onFile(event) {
    form.file = event.target.files?.[0] ?? null;
    if (form.file) submit();
}

function submit() {
    form.post(`/client/cases/${props.record.id}/documents`, {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            form.reset('file');
            if (fileInput.value) fileInput.value.value = '';
            if (cameraInput.value) cameraInput.value.value = '';
        },
    });
}

const remove = (d) => confirm('Remove this document?') && router.delete(`/client/documents/${d.id}`, { preserveScroll: true });

const size = (bytes) => bytes ? `${(bytes / 1024 / 1024).toFixed(1)} MB` : '';
const tone = (status) => ({ pending: 'attention', accepted: 'positive', rejected: 'critical' }[status] ?? 'neutral');
</script>

<template>
    <ClientLayout title="Documents" :case-id="record.id" back-href="/client">
        <div class="grid grid-cols-[minmax(0,1fr)_340px] gap-6 max-[1080px]:grid-cols-1">
            <div>
                <h2 class="mb-2 text-h2 font-semibold text-ink">Your documents</h2>
                <p class="prose-measure mb-6 text-body leading-[1.65] text-ink-70">
                    Upload the originals we asked for. Everything here is stored privately and is only
                    ever opened through a link that expires.
                </p>

                <div v-if="!documents.length" class="card p-8 text-center">
                    <p class="help">Nothing uploaded yet.</p>
                </div>

                <div class="grid gap-2">
                    <article v-for="d in documents" :key="d.id" class="card flex flex-wrap items-center justify-between gap-3 p-4">
                        <div class="min-w-0">
                            <div class="mb-1 flex flex-wrap items-center gap-2">
                                <span class="text-body-s font-medium text-ink">{{ d.category }}</span>
                                <StatusPill :tone="tone(d.status)" :label="d.status" />
                            </div>
                            <div class="tabular truncate font-mono text-caption text-slate">
                                {{ d.filename }} <span v-if="d.size">· {{ size(d.size) }}</span>
                            </div>
                            <p v-if="d.review_note" class="mt-1 text-caption text-attention">{{ d.review_note }}</p>
                        </div>
                        <div class="flex flex-none gap-2">
                            <a :href="d.url" class="btn btn-sm btn-tertiary" target="_blank" rel="noopener">Open</a>
                            <button v-if="d.status === 'pending'" type="button" class="btn btn-sm btn-tertiary text-critical" @click="remove(d)">Remove</button>
                        </div>
                    </article>
                </div>
            </div>

            <aside class="grid content-start gap-4">
                <div v-if="uploadEnabled" class="card p-5">
                    <div class="eyebrow mb-3">Add a document</div>

                    <FormField id="d-cat" label="What is it?" required>
                        <select id="d-cat" v-model="form.category" class="field">
                            <option v-for="c in categories" :key="c.value" :value="c.value">{{ c.label }}</option>
                        </select>
                    </FormField>

                    <!-- Camera first on a phone; hidden on wider screens where
                         there is usually no camera worth using. -->
                    <label class="btn btn-primary mb-2 w-full cursor-pointer max-[767px]:flex hidden">
                        Take a photo
                        <input ref="cameraInput" type="file" accept="image/*" capture="environment" class="sr-only" @change="onFile">
                    </label>

                    <label class="btn btn-secondary w-full cursor-pointer">
                        Choose a file
                        <input ref="fileInput" type="file" accept=".pdf,.jpg,.jpeg,.png,.heic,.webp,.doc,.docx" class="sr-only" @change="onFile">
                    </label>

                    <p v-if="form.errors.file" class="error mt-2">{{ form.errors.file }}</p>
                    <p v-if="form.progress" class="help mt-2 tabular">Uploading… {{ form.progress.percentage }}%</p>
                    <p class="help mt-3">PDF, image or Word. Up to 10 MB.</p>
                </div>

                <div v-else class="card p-5">
                    <p class="text-body-s text-ink">Uploads are currently closed for this matter.</p>
                </div>

                <div class="card-paper border border-rule-warm p-5">
                    <p class="text-legal leading-[1.72] text-ink">
                        Please do not send documents by email or message. This is the secure route, and it
                        is the only one we can guarantee.
                    </p>
                </div>
            </aside>
        </div>
    </ClientLayout>
</template>
