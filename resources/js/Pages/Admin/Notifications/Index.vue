<script setup>
import { Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import StatusPill from '@/Components/StatusPill.vue';

defineProps({ templates: { type: Array, default: () => [] }, recent: { type: Array, default: () => [] } });
</script>

<template>
    <AdminLayout title="Notifications">
        <div class="card-paper mb-6 border border-rule-warm p-4">
            <p class="text-body-s text-ink">
                Every message is queued, logged with its delivery status, and a failed WhatsApp message
                automatically falls back to email.
            </p>
        </div>

        <h2 class="mb-3 text-h4 font-semibold text-ink">Templates</h2>
        <div class="mb-8 grid gap-2">
            <article v-for="t in templates" :key="t.key" class="card p-4">
                <code class="mb-2 block font-mono text-caption text-gold-strong">{{ t.key }}</code>
                <div class="flex flex-wrap gap-2">
                    <Link
                        v-for="c in t.channels" :key="c.id" :href="`/admin/notifications/${c.id}`"
                        class="flex items-center gap-2 rounded-sm border border-rule-cool px-3 py-2 text-body-s hover:border-gold"
                    >
                        <span class="font-medium text-ink">{{ c.channel }}</span>
                        <StatusPill v-if="!c.is_active" tone="neutral" label="off" />
                        <StatusPill v-if="c.meta_status === 'pending_submission'" tone="attention" label="Meta approval pending" />
                    </Link>
                </div>
            </article>
        </div>

        <h2 class="mb-3 text-h4 font-semibold text-ink">Recent sends</h2>
        <div class="grid gap-1.5">
            <div v-for="(l, i) in recent" :key="i" class="card flex flex-wrap items-center justify-between gap-3 p-3">
                <div class="min-w-0">
                    <code class="font-mono text-caption text-ink">{{ l.template_key }}</code>
                    <span class="ml-2 text-caption text-slate">{{ l.channel }} → {{ l.recipient }}</span>
                    <span v-if="l.is_fallback" class="pill pill-attention ml-2">email fallback</span>
                    <p v-if="l.error" class="text-caption text-critical">{{ l.error }}</p>
                </div>
                <div class="flex flex-none items-center gap-2">
                    <StatusPill :tone="l.tone" :label="l.status" />
                    <span class="tabular font-mono text-caption text-slate">{{ new Date(l.at).toLocaleString('en-GB') }}</span>
                </div>
            </div>
            <p v-if="!recent.length" class="card p-6 text-center text-body-s text-slate">Nothing sent yet.</p>
        </div>
    </AdminLayout>
</template>
