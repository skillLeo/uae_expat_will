<script setup>
import PublicLayout from '@/Layouts/PublicLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';

const props = defineProps({
    page: { type: Object, required: true },
    sections: { type: Object, default: () => ({}) },
    structuredData: { type: [Object, Array], default: null },
});
</script>

<template>
    <PublicLayout
        :title="page.seo_title || page.title"
        :description="page.meta_description"
        :canonical="page.canonical"
        :structured-data="structuredData"
    >
        <PageHeader
            :eyebrow="page.breadcrumb"
            :breadcrumb="`Home → ${page.breadcrumb}`"
            :heading="page.title"
            :lede="page.meta_description"
        />

        <section v-for="section in sections" :key="section.key" class="bg-page pb-24 max-[719px]:pb-12">
            <div class="mx-auto max-w-[1280px] px-8 max-[719px]:px-4">
                <h2 v-if="section.heading" class="mb-4 font-display text-display-m text-ink">{{ section.heading }}</h2>
                <p v-if="section.subheading" class="prose-measure mb-8 text-body leading-[1.65] text-ink-70">{{ section.subheading }}</p>

                <!-- Profile grid: 8 circumstances, deliberately not a 3-up grid -->
                <div v-if="section.type === 'profile_grid'" class="grid grid-cols-12 gap-8">
                    <article
                        v-for="item in section.items" :key="item.n"
                        class="card col-span-6 p-6 max-[719px]:col-span-full"
                    >
                        <div class="tabular mb-3 font-mono text-body-s font-medium text-gold-strong">{{ item.n }}</div>
                        <h3 class="mb-2.5 text-h3 font-semibold text-ink">{{ item.title }}</h3>
                        <p class="mb-3 text-body-s leading-[1.6] text-ink-70">{{ item.body }}</p>
                        <p v-if="item.consider" class="border-l-2 border-gold pl-3.5 text-legal leading-[1.72] text-ink">
                            {{ item.consider }}
                        </p>
                    </article>
                </div>

                <div v-else-if="section.body" class="prose-measure text-body leading-[1.65] text-ink-70" v-html="section.body"></div>
            </div>
        </section>
    </PublicLayout>
</template>
