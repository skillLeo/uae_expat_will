<script setup>
/**
 * A legal page.
 *
 * Clause numbers sit in the margin column, the body is capped at 64ch, and the
 * clause navigation is sticky on wide screens. Nothing here is inside an
 * accordion — legal caveats must stay visible, never collapsed.
 */
import { computed } from 'vue';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';

const props = defineProps({
    page: { type: Object, required: true },
    sections: { type: Object, default: () => ({}) },
    structuredData: { type: [Object, Array], default: null },
});

const intro = computed(() => props.sections.intro ?? {});
const clauses = computed(() => props.sections.clauses?.items ?? []);
const updated = computed(() => intro.value.settings?.updated);
</script>

<template>
    <PublicLayout
        :title="page.seo_title || page.title"
        :description="page.meta_description"
        :canonical="page.canonical"
        :structured-data="structuredData"
    >
        <PageHeader
            :eyebrow="intro.subheading || page.breadcrumb"
            :breadcrumb="`Home → ${page.breadcrumb}`"
            :heading="intro.heading || page.title"
        >
            <p v-if="intro.body" class="legal-measure mt-5 text-ink">{{ intro.body }}</p>
            <p v-if="updated" class="mt-4 font-mono text-caption text-slate">Last updated {{ updated }}</p>
        </PageHeader>

        <section class="bg-page pb-24 max-[719px]:pb-12">
            <div class="mx-auto max-w-[1280px] px-8 max-[719px]:px-4">
                <div class="grid grid-cols-12 items-start gap-8">
                    <!-- Clause navigation -->
                    <nav class="col-span-3 sticky top-28 max-[1080px]:static max-[1080px]:col-span-full" aria-label="Clauses">
                        <div class="eyebrow mb-3.5">On this page</div>
                        <div class="grid border-l border-rule-warm">
                            <a
                                v-for="clause in clauses" :key="clause.anchor"
                                :href="`#${clause.anchor}`"
                                class="-ml-px border-l-2 border-transparent py-2 pl-4 text-body-s leading-[1.4] text-ink-70 hover:border-gold hover:text-gold-strong"
                            >
                                <span class="tabular font-mono text-caption text-gold-strong">{{ clause.number }}</span>
                                &nbsp;{{ clause.title }}
                            </a>
                        </div>
                    </nav>

                    <div class="col-span-8 col-start-5 max-[1080px]:col-span-full max-[1080px]:col-start-1">
                        <article
                            v-for="clause in clauses" :key="clause.anchor"
                            :id="clause.anchor"
                            class="mb-8 scroll-mt-28 border-b border-rule-warm pb-8 last:border-0"
                        >
                            <div class="margin-col">
                                <div class="margin-annot tabular font-mono text-caption text-gold-strong">{{ clause.number }}</div>
                                <div class="margin-rule"></div>
                                <div class="margin-body">
                                    <h2 class="mb-3 text-h2 font-semibold text-ink">{{ clause.title }}</h2>
                                    <p class="legal-measure text-ink-70">{{ clause.body }}</p>
                                </div>
                            </div>
                        </article>
                    </div>
                </div>
            </div>
        </section>
    </PublicLayout>
</template>
