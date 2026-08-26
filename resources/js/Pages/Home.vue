<script setup>
/**
 * The homepage.
 *
 * Every string on this page comes from `page_sections`, so an administrator can
 * edit any word of it from the content manager and see the change immediately.
 * Nothing is hardcoded here except structure.
 *
 * Ground rhythm, in order: ink-deep → page → page → ink → page → page → page →
 * ink → footer, with section padding 128/48/80/64/96. The unevenness is
 * deliberate — identical padding on every section is one of the tells the client
 * asked to avoid.
 */
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import HeroAssessmentCard from '@/Components/HeroAssessmentCard.vue';
import FaqAccordion from '@/Components/FaqAccordion.vue';

const props = defineProps({
    page: { type: Object, required: true },
    sections: { type: Object, default: () => ({}) },
    faqs: { type: Array, default: () => [] },
    structuredData: { type: [Object, Array], default: null },
});

const site = usePage();
const s = computed(() => site.props.settings ?? {});
const fee = computed(() => Number(s.value['commercial.standard_fee'] ?? 2199).toLocaleString('en-US'));

const sec = (key) => props.sections[key] ?? {};
const items = (key) => sec(key).items ?? [];
const cfg = (key) => sec(key).settings ?? {};
</script>

<template>
    <PublicLayout
        :title="page.seo_title || page.title"
        :description="page.meta_description"
        :canonical="page.canonical"
        :structured-data="structuredData"
        active=""
    >
        <!-- ========================= HERO · ink-deep ======================= -->
        <section class="on-ink bg-ink-deep py-16 max-[719px]:py-8">
            <div class="mx-auto max-w-[1280px] px-8 max-[719px]:px-4">
                <div class="grid grid-cols-12 gap-8">
                    <div class="col-span-7 max-[1080px]:col-span-full">
                        <div class="eyebrow mb-5">{{ s['branding.ownership_line'] }}</div>

                        <h1 class="mb-5 max-w-[16ch] font-display text-display-xl text-paper">
                            {{ sec('hero').heading }}
                        </h1>

                        <p class="mb-8 max-w-[58ch] text-body-l leading-[1.65] text-steel">
                            {{ sec('hero').body }}
                        </p>

                        <div class="mb-8">
                            <div class="eyebrow mb-2">Professional fee</div>
                            <div class="flex flex-wrap items-baseline gap-x-3 gap-y-1.5">
                                <div class="tabular font-mono text-[32px] font-medium leading-[1.1] text-paper">AED {{ fee }}</div>
                                <div class="text-body-s text-steel">{{ cfg('hero').fee_label }}</div>
                            </div>
                            <Link href="/pricing" class="mt-2 inline-block text-body-s font-medium text-gold-soft hover:text-paper">
                                See what is included →
                            </Link>
                        </div>

                        <div v-if="items('hero').length">
                            <div class="eyebrow mb-3">Reviewed by</div>
                            <div class="flex flex-wrap gap-x-8 gap-y-3">
                                <div v-for="person in items('hero')" :key="person.name" class="flex items-center gap-3">
                                    <img
                                        v-if="person.photo"
                                        :src="person.photo" :alt="person.name"
                                        width="44" height="44" loading="lazy" decoding="async"
                                        class="h-11 w-11 flex-none rounded-pill object-cover object-top ring-1 ring-gold-soft/40"
                                    >
                                    <div>
                                        <div class="text-body-s font-medium text-paper">{{ person.name }}</div>
                                        <div class="text-caption text-steel">{{ person.role }}</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-span-5 max-[1080px]:col-span-full">
                        <HeroAssessmentCard />
                    </div>
                </div>
            </div>
        </section>

        <!-- ==================== REASONS · page · 96/64 ===================== -->
        <section class="bg-page pb-16 pt-24 max-[719px]:py-12">
            <div class="mx-auto max-w-[1280px] px-8 max-[719px]:px-4">
                <div class="grid grid-cols-12 gap-8">
                    <div class="col-span-6 max-[1080px]:col-span-full">
                        <div class="rule-open mb-5"></div>
                        <h2 class="font-display text-display-m text-ink">{{ sec('reasons').heading }}</h2>
                    </div>
                    <p class="col-span-6 self-end text-body leading-[1.65] text-ink-70 max-[1080px]:col-span-full">
                        {{ sec('reasons').subheading }}
                    </p>
                </div>

                <div class="mt-12 grid grid-cols-12 gap-8">
                    <div
                        v-for="item in items('reasons')" :key="item.title"
                        class="card col-span-3 p-6 max-[1080px]:col-span-6 max-[719px]:col-span-full"
                    >
                        <div class="rule-open mb-4"></div>
                        <h3 class="mb-2.5 text-h3 font-semibold text-ink">{{ item.title }}</h3>
                        <p class="text-body-s leading-[1.6] text-ink-70">{{ item.body }}</p>
                    </div>
                </div>

                <div class="mt-12 grid grid-cols-12 gap-8">
                    <div class="col-span-5 max-[1080px]:col-span-full">
                        <h3 class="mb-3 text-h3 font-semibold text-ink">{{ sec('who').heading }}</h3>
                        <p class="mb-4 text-body leading-[1.65] text-ink-70">{{ sec('who').body }}</p>
                        <Link v-if="cfg('who').link" :href="cfg('who').link.href" class="text-body-s font-medium text-gold-strong">
                            {{ cfg('who').link.label }} →
                        </Link>
                    </div>
                    <div class="col-span-7 max-[1080px]:col-span-full">
                        <div class="grid grid-cols-2 gap-x-8 max-[719px]:grid-cols-1">
                            <div v-for="who in items('who')" :key="who" class="border-b border-rule-warm py-2.5 text-legal leading-[1.5] text-ink">
                                {{ who }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- ==================== PROCESS · page · 64/96 ===================== -->
        <section class="bg-page pb-24 pt-16 max-[719px]:py-12">
            <div class="mx-auto max-w-[1280px] px-8 max-[719px]:px-4">
                <div class="grid grid-cols-12 gap-8">
                    <div class="col-span-5 max-[1080px]:col-span-full">
                        <div class="rule-open mb-5"></div>
                        <h2 class="font-display text-display-m text-ink">{{ sec('process').heading }}</h2>
                    </div>
                    <p class="col-span-6 col-start-7 self-end text-body leading-[1.65] text-ink-70 max-[1080px]:col-span-full max-[1080px]:col-start-1">
                        {{ sec('process').subheading }}
                    </p>
                </div>

                <ol class="mt-12 grid grid-cols-12 gap-8">
                    <li v-for="step in items('process')" :key="step.n" class="col-span-3 max-[1080px]:col-span-6 max-[719px]:col-span-full">
                        <div class="tabular mb-3 font-mono text-body-s font-medium text-gold-strong">{{ step.n }}</div>
                        <h3 class="mb-2.5 border-t border-ink pt-3 text-h4 font-semibold text-ink">{{ step.title }}</h3>
                        <p class="text-body-s leading-[1.6] text-ink-70">{{ step.body }}</p>
                    </li>
                </ol>

                <div class="mt-8 grid grid-cols-12 gap-8">
                    <p class="card-paper col-span-8 border border-rule-warm p-6 text-legal leading-[1.72] text-ink max-[1080px]:col-span-full">
                        {{ cfg('process').caveat }}
                    </p>
                    <div v-if="cfg('process').link" class="col-span-4 self-center max-[1080px]:col-span-full">
                        <Link :href="cfg('process').link.href" class="text-body-s font-medium text-gold-strong">
                            {{ cfg('process').link.label }} →
                        </Link>
                    </div>
                </div>
            </div>
        </section>

        <!-- ====================== TRUST · ink · 96 ========================= -->
        <section class="on-ink bg-ink py-24 max-[719px]:py-12">
            <div class="mx-auto max-w-[1280px] px-8 max-[719px]:px-4">
                <div class="grid grid-cols-12 gap-8">
                    <div class="col-span-5 max-[1080px]:col-span-full">
                        <div class="rule-open mb-5"></div>
                        <h2 class="font-display text-display-m text-paper">{{ sec('trust').heading }}</h2>
                    </div>
                    <p class="col-span-6 col-start-7 self-end text-body leading-[1.65] text-steel max-[1080px]:col-span-full max-[1080px]:col-start-1">
                        {{ sec('trust').subheading }}
                    </p>
                </div>

                <div class="mt-12 grid grid-cols-12 gap-8">
                    <div v-for="item in items('trust')" :key="item.title" class="col-span-3 max-[1080px]:col-span-6 max-[719px]:col-span-full">
                        <h3 class="mb-2.5 border-t border-gold-soft pt-3 text-h4 font-semibold text-paper">{{ item.title }}</h3>
                        <p class="tabular text-body-s leading-[1.6] text-steel">{{ item.body }}</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- ==================== PATHWAYS · page · 128/48 =================== -->
        <section class="bg-page pb-12 pt-32 max-[719px]:py-12">
            <div class="mx-auto max-w-[1280px] px-8 max-[719px]:px-4">
                <div class="grid grid-cols-12 gap-8">
                    <div class="col-span-7 max-[1080px]:col-span-full">
                        <div class="rule-open mb-5"></div>
                        <h2 class="font-display text-display-m text-ink">{{ sec('pathways').heading }}</h2>
                    </div>
                    <p class="col-span-5 self-end text-body leading-[1.65] text-ink-70 max-[1080px]:col-span-full">
                        {{ sec('pathways').subheading }}
                    </p>
                </div>

                <div class="mt-12 grid grid-cols-12 gap-8">
                    <div
                        v-for="(pathway, i) in items('pathways')" :key="pathway.title"
                        class="card p-8 max-[1080px]:col-span-full max-[719px]:p-4"
                        :class="i === 0 ? 'col-span-7' : 'col-span-5 self-start'"
                    >
                        <div v-if="pathway.eyebrow" class="eyebrow mb-2.5">{{ pathway.eyebrow }}</div>
                        <span v-if="pathway.pill" class="pill pill-held mb-4">
                            <svg width="14" height="14" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                                <circle cx="7.2" cy="7.2" r="4.6" /><path d="M10.6 10.6 13.8 13.8" stroke-linecap="round" />
                            </svg>
                            {{ pathway.pill }}
                        </span>

                        <h3 class="mb-2.5 text-h3 font-semibold text-ink">{{ pathway.title }}</h3>
                        <p class="mb-4 text-body-s leading-[1.6] text-ink-70">{{ pathway.body }}</p>

                        <div class="grid">
                            <div
                                v-for="line in pathway.list" :key="line"
                                class="tabular border-b border-rule-cool py-2.5 text-legal leading-[1.5] text-ink last:border-0"
                            >{{ line }}</div>
                        </div>

                        <p v-if="pathway.footnote" class="tabular mt-4 border-t border-rule-cool pt-4 text-body-s leading-[1.6] text-ink-70">
                            {{ pathway.footnote }}
                        </p>
                        <p v-if="pathway.emphasis" class="mt-3 text-body-s font-semibold leading-[1.6] text-ink">
                            {{ pathway.emphasis }}
                        </p>
                    </div>
                </div>

                <p class="mt-6 max-w-[80ch] text-caption leading-[1.6] text-slate">{{ cfg('pathways').note }}</p>
            </div>
        </section>

        <!-- ====================== ROUTES · page · 80 ======================= -->
        <section class="bg-page py-20 max-[719px]:py-12">
            <div class="mx-auto max-w-[1280px] px-8 max-[719px]:px-4">
                <div class="grid grid-cols-12 gap-8">
                    <div class="col-span-5 max-[1080px]:col-span-full">
                        <div class="rule-open mb-5"></div>
                        <h2 class="font-display text-display-m text-ink">{{ sec('routes').heading }}</h2>
                    </div>
                    <p class="col-span-6 col-start-7 self-end text-body leading-[1.65] text-ink-70 max-[1080px]:col-span-full max-[1080px]:col-start-1">
                        {{ sec('routes').subheading }}
                    </p>
                </div>

                <div class="mt-12 grid grid-cols-12 gap-8">
                    <div v-for="route in items('routes')" :key="route.title" class="col-span-4 max-[1080px]:col-span-full">
                        <h3 class="mb-2.5 border-t border-ink pt-3 text-h3 font-semibold text-ink">{{ route.title }}</h3>
                        <p class="text-body-s leading-[1.6] text-ink-70">{{ route.body }}</p>
                    </div>
                </div>

                <div class="mt-8 grid grid-cols-12 gap-8">
                    <p class="col-span-8 text-legal leading-[1.72] text-ink-70 max-[1080px]:col-span-full">{{ cfg('routes').note }}</p>
                    <div v-if="cfg('routes').link" class="col-span-4 self-center max-[1080px]:col-span-full">
                        <Link :href="cfg('routes').link.href" class="text-body-s font-medium text-gold-strong">
                            {{ cfg('routes').link.label }} →
                        </Link>
                    </div>
                </div>
            </div>
        </section>

        <!-- ======================= FAQ · page · 64/96 ====================== -->
        <section v-if="faqs.length" class="bg-page pb-24 pt-16 max-[719px]:py-12">
            <div class="mx-auto max-w-[1280px] px-8 max-[719px]:px-4">
                <div class="mb-8 flex flex-wrap items-baseline justify-between gap-4">
                    <div>
                        <div class="rule-open mb-5"></div>
                        <h2 class="font-display text-display-m text-ink">Questions Before You Start</h2>
                    </div>
                    <Link href="/faqs" class="text-body-s font-medium text-gold-strong">View all frequently asked questions →</Link>
                </div>
                <div class="grid grid-cols-12 gap-8">
                    <div class="col-span-8 max-[1080px]:col-span-full">
                        <FaqAccordion :faqs="faqs" />
                    </div>
                </div>
            </div>
        </section>

        <!-- ======================== CTA · ink · 128 ======================== -->
        <section class="on-ink bg-ink py-32 max-[719px]:py-16">
            <div class="mx-auto max-w-[1280px] px-8 max-[719px]:px-4">
                <div class="grid grid-cols-12 gap-8">
                    <div class="col-span-7 max-[1080px]:col-span-full">
                        <div class="rule-open mb-6"></div>
                        <h2 class="mb-5 max-w-[20ch] font-display text-display-l text-paper">{{ sec('cta').heading }}</h2>
                        <p class="mb-8 max-w-[58ch] text-body-l leading-[1.65] text-steel">{{ sec('cta').body }}</p>
                        <div class="flex flex-wrap items-center gap-6">
                            <Link
                                v-if="cfg('cta').primary" :href="cfg('cta').primary.href"
                                class="btn btn-lg border-paper bg-paper text-ink hover:bg-surface"
                            >{{ cfg('cta').primary.label }}</Link>
                            <Link
                                v-if="cfg('cta').secondary" :href="cfg('cta').secondary.href"
                                class="text-legal font-medium text-paper hover:text-gold-soft"
                            >{{ cfg('cta').secondary.label }} →</Link>
                        </div>
                    </div>
                    <p class="col-span-4 col-start-9 self-start text-legal leading-[1.72] text-steel max-[1080px]:col-span-full max-[1080px]:col-start-1">
                        {{ cfg('cta').aside }}
                    </p>
                </div>
            </div>
        </section>
    </PublicLayout>
</template>
