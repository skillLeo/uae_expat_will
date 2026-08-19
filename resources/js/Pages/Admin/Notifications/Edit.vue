<script setup>
import { ref, computed } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import FormField from '@/Components/FormField.vue';
import Sheet from '@/Components/Sheet.vue';

const props = defineProps({
    template: { type: Object, required: true },
    sample: { type: Object, default: () => ({}) },
    preview: { type: Object, default: () => ({}) },
    forbidden: { type: Array, default: () => [] },
});

const form = useForm({
    subject: props.template.subject ?? '',
    body: props.template.body ?? '',
    whatsapp_header: props.template.whatsapp_header ?? '',
    whatsapp_footer: props.template.whatsapp_footer ?? '',
    is_active: props.template.is_active,
});

const testSheet = ref(false);
const recipient = ref('');

// Built here rather than in the template: Vue's parser cannot cope with the
// nested braces of a placeholder written inside an interpolation.
const placeholders = computed(() => Object.keys(props.sample).map((k) => `{{ ${k} }}`));

/** Substitutes the sample values live, so the variables can be checked. */
const rendered = computed(() => {
    const substitute = (text) => Object.entries(props.sample).reduce(
        (acc, [k, v]) => acc.replaceAll(`{{ ${k} }}`, v).replaceAll(`{{${k}}}`, v),
        text ?? '',
    );
    return { subject: substitute(form.subject), body: substitute(form.body) };
});

const sendTest = () => router.post(`/admin/notifications/${props.template.id}/test`, { recipient: recipient.value }, {
    preserveScroll: true,
    onSuccess: () => { testSheet.value = false; recipient.value = ''; },
});
</script>

<template>
    <AdminLayout :title="template.key" back-href="/admin/notifications">
        <template #action>
            <button type="button" class="btn btn-sm btn-secondary" @click="testSheet = true">Send test</button>
        </template>

        <div class="mb-6 rounded-md border border-critical-border bg-critical-bg p-4">
            <p class="mb-2 text-body-s font-semibold text-critical">What no template may ever contain</p>
            <ul class="grid gap-1">
                <li v-for="f in forbidden" :key="f" class="text-body-s text-ink">{{ f }}</li>
            </ul>
        </div>

        <div class="grid grid-cols-2 gap-6 max-[1080px]:grid-cols-1">
            <section class="card p-5">
                <div class="mb-4 flex flex-wrap items-center gap-2">
                    <h2 class="text-h4 font-semibold text-ink">{{ template.channel }} template</h2>
                    <span v-if="template.meta_status" class="pill pill-attention">{{ template.meta_status.replace('_', ' ') }}</span>
                </div>

                <FormField v-if="template.channel === 'email'" id="t-subject" label="Subject">
                    <input id="t-subject" v-model="form.subject" class="field">
                </FormField>
                <FormField v-if="template.channel === 'whatsapp'" id="t-header" label="Header">
                    <input id="t-header" v-model="form.whatsapp_header" class="field">
                </FormField>
                <FormField id="t-body" label="Body" required>
                    <textarea id="t-body" v-model="form.body" class="field min-h-[240px]"></textarea>
                </FormField>
                <FormField v-if="template.channel === 'whatsapp'" id="t-footer" label="Footer">
                    <input id="t-footer" v-model="form.whatsapp_footer" class="field">
                </FormField>

                <label class="mb-4 flex items-center gap-2.5">
                    <input v-model="form.is_active" type="checkbox" class="tap accent-gold">
                    <span class="text-body-s">Active</span>
                </label>

                <div v-if="template.variables?.length" class="mb-4">
                    <div class="eyebrow mb-2">Available variables</div>
                    <div class="flex flex-wrap gap-1.5">
                        <code v-for="v in placeholders" :key="v" class="rounded-xs border border-rule-cool px-1.5 py-0.5 font-mono text-micro text-ink-70">{{ v }}</code>
                    </div>
                </div>

                <button type="button" class="btn btn-primary" :disabled="form.processing" @click="form.patch(`/admin/notifications/${template.id}`, { preserveScroll: true })">
                    {{ form.processing ? 'Saving…' : 'Save template' }}
                </button>
            </section>

            <section>
                <h2 class="mb-3 text-h4 font-semibold text-ink">Preview with sample values</h2>
                <div class="card overflow-hidden">
                    <div v-if="template.channel === 'email'" class="border-b border-rule-cool bg-paper-sunk p-4">
                        <div class="eyebrow mb-1">Subject</div>
                        <p class="text-body-s font-medium text-ink">{{ rendered.subject }}</p>
                    </div>
                    <div v-else class="border-b border-rule-cool bg-paper-sunk p-4">
                        <p class="text-body-s font-semibold text-ink">{{ form.whatsapp_header }}</p>
                    </div>
                    <div class="p-5">
                        <p class="whitespace-pre-line text-legal leading-[1.72] text-ink">{{ rendered.body }}</p>
                    </div>
                    <div class="border-t border-rule-cool bg-paper-sunk p-4">
                        <p v-if="template.channel === 'whatsapp'" class="text-caption text-slate">{{ form.whatsapp_footer }}</p>
                        <template v-else>
                            <p class="text-caption text-ink">{{ $page.props.settings?.['branding.ownership_line'] }}</p>
                            <p class="mt-1 text-caption text-slate">
                                UAE Expat Wills and Summit Legal Consultancy UAE are not a court, registry, notary or
                                government authority.
                            </p>
                        </template>
                    </div>
                </div>
            </section>
        </div>

        <Sheet :open="testSheet" title="Send a test message" size="sm" @close="testSheet = false">
            <FormField id="t-recipient" :label="template.channel === 'email' ? 'Email address' : 'WhatsApp number'" required>
                <input
                    id="t-recipient" v-model="recipient" class="field"
                    :type="template.channel === 'email' ? 'email' : 'tel'"
                    :inputmode="template.channel === 'email' ? 'email' : 'tel'"
                >
            </FormField>
            <p class="help">Sent with the sample values shown in the preview.</p>
            <template #actions>
                <button type="button" class="btn btn-primary" @click="sendTest">Send</button>
                <button type="button" class="btn btn-tertiary" @click="testSheet = false">Cancel</button>
            </template>
        </Sheet>
    </AdminLayout>
</template>
