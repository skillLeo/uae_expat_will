<script setup>
/**
 * About Us.
 *
 * The two consultants are named because a client is entitled to know who is
 * reviewing their Will. The headshot slots are marked at 3:4 and left empty —
 * open item 05, real photographs required.
 */
import PublicLayout from '@/Layouts/PublicLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import CtaSection from '@/Components/CtaSection.vue';

const props = defineProps({
    page: { type: Object, required: true },
    sections: { type: Object, default: () => ({}) },
    structuredData: { type: [Object, Array], default: null },
});

const sec = (k) => props.sections[k] ?? {};
const items = (k) => sec(k).items ?? [];
const cfg = (k) => sec(k).settings ?? {};
</script>

<template>
    <PublicLayout
        :title="page.seo_title || page.title"
        :description="page.meta_description"
        :canonical="page.canonical"
        :structured-data="structuredData"
        active="about"
    >
        <PageHeader
            :eyebrow="sec('intro').subheading"
            breadcrumb="Home → About Us"
            :heading="sec('intro').heading"
            :lede="sec('intro').body"
        />

        <section class="bg-page pb-24 max-[719px]:pb-12">
            <div class="mx-auto max-w-[1280px] px-8 max-[719px]:px-4">
                <article
                    v-for="person in items('people')" :key="person.name"
                    class="mb-16 grid grid-cols-12 gap-8 border-b border-rule-warm pb-16 last:border-0 max-[719px]:mb-12 max-[719px]:pb-12"
                >
                    <!-- The layout mirrors on the second person. -->
                    <div
                        class="col-span-4 max-[1080px]:col-span-full"
                        :class="person.mirrored ? 'order-2 col-start-9 max-[1080px]:order-1 max-[1080px]:col-start-1' : ''"
                    >
                        <div
                            v-if="!person.photo"
                            class="grid aspect-[3/4] max-w-[360px] place-items-center border border-dashed border-steel bg-paper text-center"
                        >
                            <div>
                                <div class="font-mono text-caption text-slate">headshot · 3:4</div>
                                <div class="mt-1.5 text-body-s font-medium text-ink">{{ person.name }}</div>
                                <div class="mt-1 font-mono text-micro text-slate">awaiting photograph</div>
                            </div>
                        </div>
                        <img
                            v-else :src="person.photo" :alt="person.name"
                            class="aspect-[3/4] w-full max-w-[360px] object-cover"
                            width="360" height="480"
                        >
                    </div>

                    <div
                        class="col-span-7 max-[1080px]:col-span-full"
                        :class="person.mirrored ? 'order-1 col-start-1 max-[1080px]:order-2' : 'col-start-6'"
                    >
                        <div class="eyebrow mb-2">{{ person.role }}</div>
                        <h2 class="mb-4 font-display text-display-s text-ink">{{ person.name }}</h2>
                        <p v-for="para in person.body" :key="para" class="prose-measure mb-3 text-body leading-[1.65] text-ink-70">{{ para }}</p>

                        <dl class="mt-6 grid border-t border-rule-warm">
                            <div v-for="fact in person.facts" :key="fact.label" class="grid grid-cols-[128px_minmax(0,1fr)] gap-4 border-b border-rule-warm py-2.5 max-[719px]:grid-cols-1 max-[719px]:gap-1">
                                <dt class="text-eyebrow font-semibold uppercase tracking-[0.1em] text-slate">{{ fact.label }}</dt>
                                <dd class="text-legal leading-[1.5] text-ink">{{ fact.value }}</dd>
                            </div>
                        </dl>
                    </div>
                </article>
            </div>
        </section>

        <section class="on-ink bg-ink py-24 max-[719px]:py-12">
            <div class="mx-auto max-w-[1280px] px-8 max-[719px]:px-4">
                <div class="grid grid-cols-12 gap-8">
                    <div class="col-span-5 max-[1080px]:col-span-full">
                        <div class="rule-open mb-5"></div>
                        <h2 class="font-display text-display-m text-paper">{{ sec('commitments').heading }}</h2>
                    </div>
                    <div class="col-span-6 col-start-7 max-[1080px]:col-span-full max-[1080px]:col-start-1">
                        <div class="grid border-t border-gold-soft">
                            <div v-for="line in items('commitments')" :key="line" class="border-b border-ink-line py-3 text-body leading-[1.6] text-paper">
                                {{ line }}
                            </div>
                        </div>
                        <p class="mt-5 text-legal leading-[1.72] text-steel">{{ cfg('commitments').note }}</p>
                    </div>
                </div>
            </div>
        </section>

        <CtaSection :section="sec('cta')" />
    </PublicLayout>
</template>
