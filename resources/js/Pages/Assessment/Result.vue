<script setup>
/**
 * The result screen.
 *
 * Two rules govern this screen absolutely:
 *   - A held or urgent-review outcome NEVER shows a payment control. The control
 *     does not exist in the DOM, rather than being disabled.
 *   - The urgent-review screen names no reason at all, so it is safe to be read
 *     over the customer's shoulder by the person who may be influencing them.
 */
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import StatusPill from '@/Components/StatusPill.vue';

const props = defineProps({
    outcome: { type: String, required: true },
    tone: { type: String, default: 'neutral' },
    allowsPayment: { type: Boolean, default: false },
    reference: { type: String, default: null },
    screen: { type: Object, default: null },
    routeNote: { type: String, default: null },
    fee: { type: Object, default: () => ({}) },
});

const vat = computed(() => (props.fee.amount * props.fee.vat_rate) / 100);
const total = computed(() => props.fee.amount + vat.value);
const money = (n) => Number(n).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

const label = computed(() => ({
    continue: 'You may continue online',
    review: 'Held for legal review',
    urgent_review: 'Our team will contact you',
    stop_refer: 'A different service applies',
    stop_ineligible: 'Cannot continue online',
}[props.outcome] ?? 'Assessment complete'));
</script>

<template>
    <PublicLayout title="Your assessment result" description="Your preliminary assessment result.">
        <section class="bg-page pb-24 pt-16 max-[719px]:py-12">
            <div class="mx-auto max-w-[1280px] px-8 max-[719px]:px-4">
                <div class="grid grid-cols-12 gap-8">
                    <div class="col-span-7 max-[1080px]:col-span-full">
                        <StatusPill :tone="tone" :label="label" class="mb-6" />

                        <h1 class="mb-5 max-w-[26ch] font-display text-display-l text-ink">
                            {{ screen?.heading }}
                        </h1>

                        <p class="prose-measure mb-6 text-body-l leading-[1.65] text-ink-70">
                            {{ screen?.body }}
                        </p>

                        <div v-if="screen?.extra?.callout_heading" class="card-paper mb-6 border-l-2 border-gold p-5">
                            <p class="mb-1.5 text-body-s font-semibold leading-[1.6] text-ink">
                                {{ screen.extra.callout_heading }}
                            </p>
                            <p class="text-legal leading-[1.72] text-ink-70">{{ screen.extra.callout_body }}</p>
                        </div>

                        <p v-if="routeNote" class="card-paper mb-6 border-l-2 border-gold p-4 text-legal leading-[1.72] text-ink">
                            {{ routeNote }}
                        </p>

                        <div v-if="reference" class="mb-8">
                            <div class="eyebrow mb-1.5">Your reference</div>
                            <div class="tabular font-mono text-h3 text-ink">{{ reference }}</div>
                            <p class="help mt-1.5">Quote this if you contact us about your matter.</p>
                        </div>

                        <!-- The payment control EXISTS ONLY on a continue outcome. -->
                        <div v-if="allowsPayment" class="flex flex-wrap items-center gap-4">
                            <Link href="/pricing" class="btn btn-primary btn-lg">
                                {{ screen?.primary_action_label ?? 'View the service and fees' }}
                            </Link>
                            <Link href="/contact" class="text-legal font-medium text-ink underline decoration-gold underline-offset-4">
                                {{ screen?.secondary_action_label ?? 'I have a question before continuing' }}
                            </Link>
                        </div>
                        <div v-else class="flex flex-wrap items-center gap-4">
                            <Link href="/contact" class="btn btn-primary btn-lg">
                                {{ screen?.primary_action_label ?? 'Contact our team' }}
                            </Link>
                        </div>

                        <p v-if="screen?.extra?.reassurance" class="mt-4 text-caption text-slate">
                            {{ screen.extra.reassurance }}
                        </p>

                        <p v-if="!allowsPayment" class="mt-6 text-legal leading-[1.72] text-ink-70">
                            No payment is requested at this stage.
                        </p>

                        <!-- What the fee buys, exactly as the approved handoff lists it. -->
                        <section v-if="screen?.extra?.includes?.length" class="mt-10">
                            <h2 class="eyebrow mb-4">{{ screen.extra.includes_heading ?? 'What the professional fee includes' }}</h2>
                            <dl class="grid gap-0">
                                <div
                                    v-for="([label, detail], i) in screen.extra.includes" :key="i"
                                    class="grid grid-cols-[190px_1fr] gap-5 border-b border-rule-warm py-3 last:border-0 max-[719px]:grid-cols-1 max-[719px]:gap-1"
                                >
                                    <dt class="text-body-s font-medium text-ink">{{ label }}</dt>
                                    <dd class="text-body-s leading-[1.6] text-ink-70">{{ detail }}</dd>
                                </div>
                            </dl>
                        </section>

                        <!-- Not a footnote. Payment is not registration, and the
                             handoff is emphatic that this must be unmissable. -->
                        <aside v-if="screen?.extra?.notice_heading" class="card-paper mt-10 border-l-2 border-gold p-5">
                            <p class="mb-2 text-body-s font-semibold leading-[1.6] text-ink">
                                {{ screen.extra.notice_heading }}
                            </p>
                            <p class="text-legal leading-[1.72] text-ink-70">{{ screen.extra.notice_body }}</p>
                        </aside>
                    </div>

                    <!-- The fee breakdown, again only where payment is possible. -->
                    <aside v-if="allowsPayment" class="card col-span-4 col-start-9 self-start p-6 max-[1080px]:col-span-full max-[1080px]:col-start-1">
                        <div class="eyebrow mb-4">What you would pay</div>
                        <dl class="grid gap-2.5">
                            <div class="flex items-baseline justify-between gap-4 border-b border-rule-cool pb-2.5">
                                <dt class="text-body-s text-ink-70">Professional fee</dt>
                                <dd class="tabular font-mono text-body-s text-ink">{{ money(fee.amount) }}</dd>
                            </div>
                            <div class="flex items-baseline justify-between gap-4 border-b border-rule-cool pb-2.5">
                                <dt class="text-body-s text-ink-70">VAT at {{ fee.vat_rate }}%</dt>
                                <dd class="tabular font-mono text-body-s text-ink">{{ money(vat) }}</dd>
                            </div>
                            <div class="flex items-baseline justify-between gap-4 pt-1">
                                <dt class="text-body-s font-semibold text-ink">Payable at checkout</dt>
                                <dd class="tabular font-mono text-body font-medium text-ink">{{ fee.currency }} {{ money(total) }}</dd>
                            </div>
                        </dl>
                        <p class="help mt-4 border-t border-rule-cool pt-4">
                            {{ screen?.extra?.authority_fees_note
                                ?? 'Government, court, registry and notary charges are separate and are set by the authority, not by us.' }}
                        </p>
                    </aside>
                </div>

                <p class="prose-measure mt-12 border-t border-rule-warm pt-6 text-legal leading-[1.72] text-ink-70">
                    This result is preliminary. It is not a legal opinion, not final acceptance by any
                    authority, and it does not mean that a Will has been prepared or registered.
                </p>
            </div>
        </section>
    </PublicLayout>
</template>
