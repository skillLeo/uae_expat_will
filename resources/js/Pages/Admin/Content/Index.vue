<script setup>
import { Link, router, usePage } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import StatusPill from '@/Components/StatusPill.vue';

defineProps({
    pages: { type: Array, default: () => [] },
    faqCount: { type: Number, default: 0 },
    postCount: { type: Number, default: 0 },
    draftCount: { type: Number, default: 0 },
    categoryCount: { type: Number, default: 0 },
});

const page = usePage();
const can = (p) => (page.props.auth?.permissions ?? []).includes(p);

const togglePublish = (p) => router.post(`/admin/content/${p.id}/publish`, { is_published: !p.is_published }, { preserveScroll: true });
</script>

<template>
    <AdminLayout title="Content">
        <div class="mb-6 grid grid-cols-2 gap-4 max-[719px]:grid-cols-1">
            <Link href="/admin/content/posts" class="card flex items-center justify-between gap-4 p-5 hover:border-gold">
                <div>
                    <div class="text-body font-semibold text-ink">Insights</div>
                    <div class="help">{{ postCount }} articles, {{ draftCount }} in draft</div>
                </div>
                <span class="text-gold-strong" aria-hidden="true">→</span>
            </Link>

            <Link href="/admin/content/faqs" class="card flex items-center justify-between gap-4 p-5 hover:border-gold">
                <div>
                    <div class="text-body font-semibold text-ink">Frequently asked questions</div>
                    <div class="help">{{ faqCount }} questions in {{ categoryCount }} categories</div>
                </div>
                <span class="text-gold-strong" aria-hidden="true">→</span>
            </Link>
            <div class="card-paper border border-rule-warm p-5">
                <p class="text-body-s leading-[1.6] text-ink">
                    Copy is Summit's. Edit it here, but do not rewrite their legal wording — the contract
                    forbids altering it.
                </p>
            </div>
        </div>

        <h2 class="mb-3 text-h4 font-semibold text-ink">Pages</h2>
        <div class="grid gap-2">
            <article v-for="p in pages" :key="p.id" class="card flex flex-wrap items-center justify-between gap-4 p-4">
                <div class="min-w-0">
                    <div class="mb-1 flex flex-wrap items-center gap-2">
                        <span class="text-body-s font-semibold text-ink">{{ p.title }}</span>
                        <StatusPill :tone="p.is_published ? 'positive' : 'neutral'" :label="p.is_published ? 'Live' : 'Hidden'" />
                    </div>
                    <code class="font-mono text-caption text-slate">{{ p.slug }}</code>
                    <span class="ml-2 text-caption text-slate">{{ p.sections_count }} sections</span>
                </div>
                <div class="flex flex-none gap-2">
                    <Link :href="`/admin/content/${p.id}`" class="btn btn-sm btn-secondary">Edit</Link>
                    <button v-if="can('content.publish')" type="button" class="btn btn-sm btn-tertiary" @click="togglePublish(p)">
                        {{ p.is_published ? 'Unpublish' : 'Publish' }}
                    </button>
                </div>
            </article>
        </div>
    </AdminLayout>
</template>
