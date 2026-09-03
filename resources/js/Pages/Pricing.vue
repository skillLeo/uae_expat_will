<script setup>
/**
 * Pricing.
 *
 * The DIFC figure is never rendered as a fixed purchasable price: always
 * "from", always quoted individually, never payable online. Every money figure
 * is tabular so the columns line up.
 */
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import CtaSection from '@/Components/CtaSection.vue';

const props = defineProps({
    page: { type: Object, required: true },
    sections: { type: Object, default: () => ({}) },
    structuredData: { type: [Object, Array], default: null },
});

const site = usePage();
const s = computed(() => site.props.settings ?? {});
const fee = computed(() => Number(s.value['commercial.standard_fee'] ?? 2199).toLocaleString('en-US'));
const mirrorFee = computed(() => Number(s.value['commercial.mirror_fee'] ?? 2999).toLocaleString('en-US'));
const difcFee = computed(() => Number(s.value['commercial.difc_starting_fee'] ?? 3999).toLocaleString('en-US'));
const authorityFees = computed(() => s.value['commercial.authority_fees'] ?? []);

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
        active="pricing"
    >
        <PageHeader
            :eyebrow="sec('intro').subheading"
            breadcrumb="Home → Pricing"
            :heading="sec('intro').heading"
            :lede="sec('intro').body"
        >
            <p class="prose-measure mt-4 text-body leading-[1.65] text-ink-70">{{ cfg('intro').second }}</p>
        </PageHeader>

        <section class="bg-page pb-24 max-[719px]:pb-12">
            <div class="mx-auto max-w-[1280px] px-8 max-[719px]:px-4">
                <!--
                    One grid, no empty columns.

                    The Mirror card used to sit in a second grid of its own at
                    col-span-7, which left columns 8-12 of that row completely
                    blank: a card beside 42% of nothing, which is what the
                    client screenshotted. The standard Will has the most to
                    say so it keeps the wide column; the mirror pair and the
                    DIFC route stack beside it and together fill the height.
                -->
                <div class="grid grid-cols-12 gap-8">
                    <!-- Standard fee: columns 1-7 -->
                    <div class="card card-accent col-span-7 p-8 max-[1080px]:col-span-full max-[719px]:p-4">
                        <div class="eyebrow mb-4">{{ sec('fee').heading }}</div>
                        <div class="mb-2 flex flex-wrap items-baseline gap-x-4 gap-y-2">
                            <div class="tabular font-mono text-[40px] font-medium leading-[1.05] tracking-[-0.015em] text-ink">AED {{ fee }}</div>
                            <div class="text-body text-ink-70">plus VAT</div>
                        </div>
                        <p class="mb-6 max-w-[56ch] text-body leading-[1.65] text-ink-70">{{ sec('fee').body }}</p>

                        <div class="grid grid-cols-2 gap-8 max-[719px]:grid-cols-1">
                            <div v-for="col in items('fee')" :key="col.heading">
                                <div class="mb-1 border-b-[1.5px] border-ink pb-2.5 text-body-s font-semibold text-ink">{{ col.heading }}</div>
                                <div v-for="line in col.items" :key="line" class="border-b border-rule-cool py-2.5 text-legal leading-[1.5] text-ink last:border-0">
                                    {{ line }}
                                </div>
                                <p v-if="col.footnote" class="mt-4 text-legal font-semibold leading-[1.72] text-ink">{{ col.footnote }}</p>
                            </div>
                        </div>

                        <div class="mt-6 border-t border-rule-cool pt-5">
                            <p class="legal-measure text-ink-70">{{ cfg('fee').note }}</p>
                        </div>
                    </div>

                    <!-- Columns 8-12: the mirror pair, then the DIFC route. -->
                    <div class="col-span-5 flex flex-col gap-8 max-[1080px]:col-span-full">
                        <!-- Mirror Wills: a second purchasable price, not "from" like DIFC -->
                        <div class="card card-accent p-8 max-[719px]:p-4">
                            <div class="eyebrow mb-4">{{ sec('mirror_fee').heading }}</div>
                            <div class="mb-2 flex flex-wrap items-baseline gap-x-4 gap-y-2">
                                <div class="tabular font-mono text-[40px] font-medium leading-[1.05] tracking-[-0.015em] text-ink">AED {{ mirrorFee }}</div>
                                <div class="text-body text-ink-70">plus VAT</div>
                            </div>
                            <p class="mb-6 text-body leading-[1.65] text-ink-70">{{ sec('mirror_fee').body }}</p>

                            <div class="grid gap-3">
                                <p v-for="line in items('mirror_fee')" :key="line" class="border-b border-rule-cool py-2.5 text-legal leading-[1.5] text-ink last:border-0">
                                    {{ line }}
                                </p>
                            </div>

                            <div class="mt-6 border-t border-rule-cool pt-5">
                                <p class="legal-measure text-ink-70">{{ cfg('mirror_fee').note }}</p>
                            </div>
                        </div>

                        <!-- DIFC. Never a purchasable price. -->
                        <div class="card p-8 max-[719px]:p-4">
                            <span class="pill pill-held mb-4">
                                <svg width="15" height="15" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                                    <circle cx="7.2" cy="7.2" r="4.6" /><path d="M10.6 10.6 13.8 13.8" stroke-linecap="round" />
                                </svg>
                                {{ cfg('difc').pill }}
                            </span>
                            <div class="eyebrow mb-3">{{ sec('difc').heading }}</div>
                            <div class="mb-4 flex flex-wrap items-baseline gap-x-3 gap-y-2">
                                <div class="tabular font-mono text-[30px] font-medium leading-[1.1] text-ink">From AED {{ difcFee }}</div>
                                <div class="text-legal text-ink-70">plus VAT</div>
                            </div>
                            <p v-for="para in items('difc')" :key="para" class="mb-3 text-legal leading-[1.72] text-ink-70">{{ para }}</p>
                            <Link
                                v-if="cfg('difc').cta" :href="cfg('difc').cta.href"
                                class="btn btn-secondary mt-2"
                            >{{ cfg('difc').cta.label }}</Link>
                            <p class="help mt-4">{{ cfg('difc').note }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Authority fees. Figures come from settings so they stay current. -->
        <section class="bg-page pb-24 max-[719px]:pb-12">
            <div class="mx-auto max-w-[1280px] px-8 max-[719px]:px-4">
                <div class="grid grid-cols-12 gap-8">
                    <div class="col-span-6 max-[1080px]:col-span-full">
                        <div class="rule-open mb-5"></div>
                        <h2 class="font-display text-display-m text-ink">{{ sec('authority_fees').heading }}</h2>
                    </div>
                    <p class="col-span-6 self-end text-body leading-[1.65] text-ink-70 max-[1080px]:col-span-full">
                        {{ sec('authority_fees').subheading }}
                    </p>
                </div>

                <div class="card mt-10 overflow-hidden max-[719px]:hidden">
                    <table class="data-table">
                        <caption class="sr-only">Expected authority charges by route</caption>
                        <thead>
                            <tr>
                                <th scope="col">{{ cfg('authority_fees').columns?.[0] }}</th>
                                <th scope="col" class="text-right">{{ cfg('authority_fees').columns?.[1] }}</th>
                                <th scope="col">{{ cfg('authority_fees').columns?.[2] }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="row in authorityFees" :key="row.route">
                                <th scope="row" class="bg-transparent text-legal font-medium normal-case tracking-normal text-ink">{{ row.route }}</th>
                                <td class="tabular text-right font-mono text-legal text-ink">{{ row.amount }}</td>
                                <td class="text-body-s text-ink-70">{{ row.note }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="mt-8 hidden grid gap-2 max-[719px]:grid">
                    <div v-for="row in authorityFees" :key="row.route" class="card p-4">
                        <div class="mb-3 text-body font-semibold text-ink">{{ row.route }}</div>
                        <dl class="grid grid-cols-[128px_1fr] gap-x-3 gap-y-2 text-body-s">
                            <dt class="text-slate">Authority charge</dt>
                            <dd class="tabular font-mono text-ink">{{ row.amount }}</dd>
                            <dt class="text-slate">Note</dt>
                            <dd class="text-ink-70">{{ row.note }}</dd>
                        </dl>
                    </div>
                </div>

                <div class="card-paper mt-6 max-w-[80%] border border-rule-warm p-6 max-[1080px]:max-w-full">
                    <p class="legal-measure text-ink">{{ cfg('authority_fees').note }}</p>
                </div>
            </div>
        </section>

        <!-- Commercial terms -->
        <section class="bg-page pb-32 max-[719px]:pb-12">
            <div class="mx-auto max-w-[1280px] px-8 max-[719px]:px-4">
                <div class="grid grid-cols-12 gap-8">
                    <div v-for="card in items('terms')" :key="card.title" class="col-span-4 max-[1080px]:col-span-full">
                        <div class="rule-open mb-4"></div>
                        <h2 class="mb-3 text-h2 font-semibold text-ink">{{ card.title }}</h2>
                        <p v-if="card.body" class="text-body leading-[1.65] text-ink-70">{{ card.body }}</p>
                        <div v-if="card.list" class="grid border-t border-rule-warm">
                            <div v-for="line in card.list" :key="line" class="border-b border-rule-warm py-2.5 text-legal leading-[1.5] text-ink">
                                {{ line }}
                            </div>
                        </div>
                        <p v-if="card.footnote" class="mt-4 text-legal font-semibold leading-[1.72] text-ink">{{ card.footnote }}</p>
                    </div>
                </div>
            </div>
        </section>

        <CtaSection :section="sec('cta')" />
    </PublicLayout>
</template>
