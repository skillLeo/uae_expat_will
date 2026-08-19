<script setup>
/**
 * The FAQ page: 57 questions in 8 categories.
 *
 * Every answer is present in the server-rendered HTML even when collapsed. The
 * filter is client-side only and produces NO indexable URLs — filtering must not
 * mint a hundred thin pages. There is no pagination.
 */
import { ref, computed } from 'vue';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import FaqAccordion from '@/Components/FaqAccordion.vue';

const props = defineProps({
    page: { type: Object, required: true },
    categories: { type: Array, default: () => [] },
    faqs: { type: Array, default: () => [] },
    structuredData: { type: [Object, Array], default: null },
});

const query = ref('');

const filtered = computed(() => {
    const q = query.value.trim().toLowerCase();
    if (!q) return props.faqs;
    return props.faqs.filter(
        (f) => f.question.toLowerCase().includes(q) || f.answer.toLowerCase().includes(q),
    );
});

const grouped = computed(() =>
    props.categories
        .map((c) => ({ ...c, items: filtered.value.filter((f) => f.category_key === c.key) }))
        .filter((c) => c.items.length > 0),
);
</script>

<template>
    <PublicLayout
        :title="page.seo_title || page.title"
        :description="page.meta_description"
        :canonical="page.canonical"
        :structured-data="structuredData"
        active="faqs"
    >
        <PageHeader
            eyebrow="FAQs"
            breadcrumb="Home → FAQs"
            heading="Frequently asked questions"
            lede="Everything below is answered in full on this page. Nothing is hidden behind a link, and no answer is shortened."
        />

        <section class="bg-page pb-24 max-[719px]:pb-12">
            <div class="mx-auto max-w-[1280px] px-8 max-[719px]:px-4">
                <div class="grid grid-cols-12 items-start gap-8">
                    <nav class="col-span-3 sticky top-28 max-[1080px]:static max-[1080px]:col-span-full" aria-label="Categories">
                        <label class="label" for="faq-filter">Filter questions</label>
                        <input
                            id="faq-filter" v-model="query" type="search" class="field mb-6"
                            placeholder="Type to filter" inputmode="search" autocomplete="off"
                        >
                        <div class="eyebrow mb-3.5">Categories</div>
                        <div class="grid border-l border-rule-warm">
                            <a
                                v-for="c in grouped" :key="c.key" :href="`#cat-${c.key}`"
                                class="-ml-px border-l-2 border-transparent py-2 pl-4 text-body-s leading-[1.4] text-ink-70 hover:border-gold hover:text-gold-strong"
                            >
                                {{ c.label }}
                                <span class="tabular font-mono text-caption text-slate">({{ c.items.length }})</span>
                            </a>
                        </div>
                    </nav>

                    <div class="col-span-8 col-start-5 max-[1080px]:col-span-full max-[1080px]:col-start-1">
                        <div v-if="grouped.length === 0" class="card p-8 text-center">
                            <p class="text-body font-medium text-ink">No matching question</p>
                            <p class="help mt-2">
                                Nothing here matches “{{ query }}”. Try a different word, or
                                <a href="/contact" class="text-gold-strong underline">contact our team</a>.
                            </p>
                        </div>

                        <section v-for="c in grouped" :key="c.key" :id="`cat-${c.key}`" class="mb-12 scroll-mt-28">
                            <h2 class="mb-4 font-display text-display-s text-ink">{{ c.label }}</h2>
                            <FaqAccordion :faqs="c.items" />
                        </section>
                    </div>
                </div>
            </div>
        </section>
    </PublicLayout>
</template>
