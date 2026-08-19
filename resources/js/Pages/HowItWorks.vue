<script setup>
/**
 * How It Works — the nine-step journey.
 *
 * Every step names its actor (You / Summit / The authority), because the
 * difference between what the firm does and what the authority decides is
 * exactly where unkeepable promises come from.
 */
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import CtaSection from '@/Components/CtaSection.vue';
import { useDrawOnScroll } from '@/Composables/useDrawOnScroll';

const props = defineProps({
    page: { type: Object, required: true },
    sections: { type: Object, default: () => ({}) },
    structuredData: { type: [Object, Array], default: null },
});

const sec = (k) => props.sections[k] ?? {};
const items = (k) => sec(k).items ?? [];
const cfg = (k) => sec(k).settings ?? {};

const { rail, fill, lit } = useDrawOnScroll();

const anchors = computed(() =>
    items('steps').map((s) => ({ href: `#${s.id}`, label: `${s.n} · ${s.title}` })),
);
</script>

<template>
    <PublicLayout
        :title="page.seo_title || page.title"
        :description="page.meta_description"
        :canonical="page.canonical"
        :structured-data="structuredData"
        active="how"
    >
        <PageHeader
            :eyebrow="sec('intro').subheading"
            breadcrumb="Home → How It Works"
            :heading="sec('intro').heading"
            :lede="sec('intro').body"
        />

        <!-- The nine steps -->
        <section class="bg-page pb-24 max-[719px]:pb-12">
            <div class="mx-auto max-w-[1280px] px-8 max-[719px]:px-4">
                <div class="grid grid-cols-12 items-start gap-8">
                    <nav class="col-span-3 sticky top-28 max-[1080px]:static max-[1080px]:col-span-full max-[1080px]:mb-8" aria-label="Steps">
                        <div class="eyebrow mb-3.5">On this page</div>
                        <div class="grid border-l border-rule-warm">
                            <a
                                v-for="a in anchors" :key="a.href" :href="a.href"
                                class="-ml-px border-l-2 border-transparent py-2 pl-4 text-body-s leading-[1.4] text-ink-70 hover:border-gold hover:text-gold-strong"
                            >{{ a.label }}</a>
                        </div>
                    </nav>

                    <div class="col-span-9 max-[1080px]:col-span-full">
                        <ol ref="rail" class="relative grid">
                            <!-- The rail: a static hairline, and the gold line drawn over it. -->
                            <div class="absolute bottom-0 left-[19px] top-0 w-px bg-rule-warm" aria-hidden="true"></div>
                            <div
                                ref="fill"
                                class="absolute bottom-0 left-[19px] top-0 w-px origin-top bg-gold"
                                style="transform: scaleY(0)"
                                aria-hidden="true"
                            ></div>

                            <li
                                v-for="(step, i) in items('steps')" :key="step.n"
                                :id="step.id"
                                class="relative grid scroll-mt-28 grid-cols-[40px_minmax(0,1fr)] items-start gap-6 pb-10"
                            >
                                <div
                                    data-marker
                                    class="relative z-10 grid h-10 w-10 place-items-center rounded-pill border transition-colors duration-200"
                                    :class="lit.has(i)
                                        ? 'border-gold bg-[#F2EAD8]'
                                        : 'border-rule-warm bg-page'"
                                >
                                    <span
                                        class="tabular font-mono text-caption font-medium transition-colors duration-200"
                                        :class="lit.has(i) ? 'text-gold-strong' : 'text-slate'"
                                    >{{ step.n }}</span>
                                </div>

                                <div class="pt-1.5">
                                    <div class="mb-2 flex flex-wrap items-baseline gap-3">
                                        <h2 class="text-h2 font-semibold text-ink">{{ step.title }}</h2>
                                        <span class="flex-none whitespace-nowrap rounded-xs border border-rule-warm px-1.5 py-0.5 font-mono text-[11px] uppercase tracking-[0.06em] text-slate">
                                            {{ step.actor }}
                                        </span>
                                    </div>
                                    <p class="prose-measure mb-2.5 text-body leading-[1.65] text-ink-70">{{ step.body }}</p>
                                    <p v-if="step.note" class="legal-measure border-l-2 border-gold pl-3.5 text-ink">{{ step.note }}</p>
                                </div>
                            </li>
                        </ol>
                    </div>
                </div>
            </div>
        </section>

        <!-- Responsibilities: three columns wide, three labelled cards narrow -->
        <section id="responsibilities" class="on-ink bg-ink py-24 max-[719px]:py-12">
            <div class="mx-auto max-w-[1280px] px-8 max-[719px]:px-4">
                <div class="grid grid-cols-12 gap-8">
                    <div class="col-span-5 max-[1080px]:col-span-full">
                        <div class="rule-open mb-5"></div>
                        <h2 class="font-display text-display-m text-paper">{{ sec('responsibilities').heading }}</h2>
                    </div>
                    <p class="col-span-6 col-start-7 self-end text-body leading-[1.65] text-steel max-[1080px]:col-span-full max-[1080px]:col-start-1">
                        {{ sec('responsibilities').subheading }}
                    </p>
                </div>

                <!-- Wide: a real three-column matrix -->
                <div class="mt-12 border-t-[1.5px] border-paper max-[900px]:hidden">
                    <div class="grid grid-cols-3 gap-8 pb-5 pt-4">
                        <div v-for="col in cfg('responsibilities').columns" :key="col" class="eyebrow text-gold-soft">{{ col }}</div>
                    </div>
                    <div
                        v-for="(row, i) in items('responsibilities')" :key="i"
                        class="grid grid-cols-3 gap-8 border-t border-ink-line py-4"
                    >
                        <div class="text-legal leading-[1.6] text-paper">{{ row.summit }}</div>
                        <div class="text-legal leading-[1.6] text-paper">{{ row.you }}</div>
                        <div class="text-legal leading-[1.6] text-paper">{{ row.authority }}</div>
                    </div>
                </div>

                <!-- Narrow: three labelled cards. No horizontal scroll, ever. -->
                <div class="mt-8 hidden grid gap-4 max-[900px]:grid">
                    <div
                        v-for="(col, ci) in cfg('responsibilities').columns" :key="col"
                        class="border border-ink-line p-5"
                    >
                        <div class="eyebrow mb-3 text-gold-soft">{{ col }}</div>
                        <div class="grid gap-2.5">
                            <div
                                v-for="(row, i) in items('responsibilities')" :key="i"
                                class="border-b border-ink-line pb-2.5 text-legal leading-[1.6] text-paper last:border-0 last:pb-0"
                            >
                                {{ ci === 0 ? row.summit : ci === 1 ? row.you : row.authority }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Timing -->
        <section class="bg-page py-24 max-[719px]:py-12">
            <div class="mx-auto max-w-[1280px] px-8 max-[719px]:px-4">
                <div class="grid grid-cols-12 gap-8">
                    <div class="card-paper col-span-7 border border-rule-warm p-6 max-[1080px]:col-span-full">
                        <h2 class="mb-2.5 text-h3 font-semibold text-ink">{{ sec('timing').heading }}</h2>
                        <p class="legal-measure mb-2.5 text-ink">{{ sec('timing').body }}</p>
                        <p class="legal-measure text-ink">{{ cfg('timing').second }}</p>
                    </div>
                    <div class="col-span-4 col-start-9 self-start max-[1080px]:col-span-full max-[1080px]:col-start-1">
                        <div class="rule-open mb-4"></div>
                        <p class="legal-measure text-ink-70">
                            Related: the five registration routes and how they differ are set out on
                            <Link href="/uae-will-registration-options" class="text-gold-strong underline decoration-gold underline-offset-4">UAE Will options</Link>,
                            and every charge is itemised on
                            <Link href="/pricing" class="text-gold-strong underline decoration-gold underline-offset-4">pricing</Link>.
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <CtaSection :section="sec('cta')" />
    </PublicLayout>
</template>
