<script setup>
/**
 * UAE Will Options — the five registration routes.
 *
 * There is deliberately NO call to action inside any route section. Nothing
 * should push a decision while the reader is still working out which route they
 * are even in; the single CTA sits at the very end.
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
        active="options"
    >
        <PageHeader
            :eyebrow="sec('intro').subheading"
            breadcrumb="Home → UAE Will Options"
            :heading="sec('intro').heading"
            :lede="sec('intro').body"
        >
            <p v-if="cfg('intro').note" class="legal-measure mt-4 text-ink">{{ cfg('intro').note }}</p>
        </PageHeader>

        <!-- The comparison. A table at width, five labelled cards below 900px. -->
        <section class="bg-page pb-20 max-[719px]:pb-12">
            <div class="mx-auto max-w-[1280px] px-8 max-[719px]:px-4">
                <div class="card overflow-hidden max-[900px]:hidden">
                    <table class="data-table">
                        <caption class="sr-only">The five UAE Will routes compared</caption>
                        <thead>
                            <tr><th v-for="c in cfg('routes_table').columns" :key="c" scope="col">{{ c }}</th></tr>
                        </thead>
                        <tbody>
                            <tr v-for="route in items('routes_table')" :key="route.name">
                                <th scope="row" class="bg-transparent text-body-s font-semibold normal-case tracking-normal text-ink">{{ route.name }}</th>
                                <td class="text-ink-70">{{ route.who }}</td>
                                <td class="text-ink-70">{{ route.features }}</td>
                                <td class="text-ink-70">{{ route.handled }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="hidden grid gap-3 max-[900px]:grid">
                    <article v-for="route in items('routes_table')" :key="route.name" class="card p-5">
                        <h2 class="mb-3.5 text-h4 font-semibold leading-[1.35] text-ink">{{ route.name }}</h2>
                        <dl class="grid gap-3">
                            <div v-for="[label, value] in [
                                ['Who it may be relevant for', route.who],
                                ['Main features', route.features],
                                ['How it is handled', route.handled],
                            ]" :key="label">
                                <dt class="mb-1 text-eyebrow font-semibold uppercase tracking-[0.1em] text-slate">{{ label }}</dt>
                                <dd class="text-legal leading-[1.6] text-ink">{{ value }}</dd>
                            </div>
                        </dl>
                    </article>
                </div>

                <div class="mt-8 grid grid-cols-12 gap-8">
                    <div class="col-span-6 max-[1080px]:col-span-full">
                        <h2 class="mb-2.5 text-h3 font-semibold text-ink">{{ sec('beyond').heading }}</h2>
                        <p class="text-body leading-[1.65] text-ink-70">{{ sec('beyond').body }}</p>
                    </div>
                    <div class="card-paper col-span-6 border border-rule-warm p-6 max-[1080px]:col-span-full">
                        <h2 class="mb-2.5 text-h3 font-semibold text-ink">{{ cfg('beyond').second_heading }}</h2>
                        <p class="legal-measure text-ink">{{ cfg('beyond').second }}</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Route detail. No CTA anywhere in here. -->
        <section class="bg-page pb-24 max-[719px]:pb-12">
            <div class="mx-auto max-w-[1280px] px-8 max-[719px]:px-4">
                <div class="grid grid-cols-12 items-start gap-8">
                    <nav class="col-span-3 sticky top-28 max-[1080px]:static max-[1080px]:col-span-full max-[1080px]:mb-8" aria-label="Routes">
                        <div class="eyebrow mb-3.5">On this page</div>
                        <div class="grid border-l border-rule-warm">
                            <a
                                v-for="a in cfg('detail').anchors" :key="a.href" :href="a.href"
                                class="-ml-px border-l-2 border-transparent py-2 pl-4 text-body-s leading-[1.4] text-ink-70 hover:border-gold hover:text-gold-strong"
                            >{{ a.label }}</a>
                        </div>
                    </nav>

                    <div class="col-span-9 max-[1080px]:col-span-full">
                        <article
                            v-for="route in items('detail')" :key="route.anchor"
                            :id="route.anchor"
                            class="mb-10 scroll-mt-28 border-b border-rule-warm pb-10 last:border-0"
                        >
                            <div class="mb-3 flex flex-wrap items-baseline gap-3">
                                <h2 class="font-display text-h1 text-ink">{{ route.title }}</h2>
                                <span v-if="route.pill" class="pill pill-held">
                                    <svg width="14" height="14" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                                        <circle cx="7.2" cy="7.2" r="4.6" /><path d="M10.6 10.6 13.8 13.8" stroke-linecap="round" />
                                    </svg>
                                    {{ route.pill }}
                                </span>
                            </div>

                            <p class="prose-measure mb-4 text-body leading-[1.65] text-ink-70">{{ route.body }}</p>

                            <div v-if="route.columns" class="grid grid-cols-2 gap-8 max-[719px]:grid-cols-1">
                                <div v-for="col in route.columns" :key="col.heading">
                                    <div class="mb-1 border-b-[1.5px] border-ink pb-2.5 text-body-s font-semibold text-ink">{{ col.heading }}</div>
                                    <div v-for="item in col.items" :key="item" class="border-b border-rule-warm py-2.5 text-legal leading-[1.5] text-ink last:border-0">
                                        {{ item }}
                                    </div>
                                </div>
                            </div>

                            <div v-if="route.steps" class="mt-5 grid grid-cols-3 gap-6 border-t border-rule-warm pt-5 max-[719px]:grid-cols-1">
                                <div v-for="s in route.steps" :key="s.n">
                                    <div class="tabular mb-2 font-mono text-caption text-gold-strong">{{ s.n }}</div>
                                    <div class="text-legal leading-[1.6] text-ink">{{ s.body }}</div>
                                </div>
                            </div>

                            <p v-if="route.footnote" class="legal-measure mt-4 text-ink-70">{{ route.footnote }}</p>
                        </article>
                    </div>
                </div>
            </div>
        </section>

        <CtaSection :section="sec('cta')" />
    </PublicLayout>
</template>
