<script setup>
/**
 * The contact page.
 *
 * There is NO FORM here, and there must never be one. Email and WhatsApp as live
 * text links only — a written client rule, because matter detail sent through a
 * web form is matter detail sent over an unsecured channel.
 */
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';

defineProps({
    page: { type: Object, required: true },
    sections: { type: Object, default: () => ({}) },
    structuredData: { type: [Object, Array], default: null },
});

const site = usePage();
const s = computed(() => site.props.settings ?? {});
const waNumber = computed(() => String(s.value['contact.whatsapp_number'] ?? '').replace(/[^0-9]/g, ''));
</script>

<template>
    <PublicLayout
        :title="page.seo_title || page.title"
        :description="page.meta_description"
        :canonical="page.canonical"
        :structured-data="structuredData"
        active="contact"
    >
        <PageHeader
            eyebrow="Contact"
            breadcrumb="Home → Contact"
            heading="Contact our team"
        />

        <section class="bg-page pb-24 max-[719px]:pb-12">
            <div class="mx-auto max-w-[1280px] px-8 max-[719px]:px-4">
                <div class="grid grid-cols-12 gap-8">
                    <!-- The warning comes first and occupies columns 1–7. -->
                    <div class="card-paper col-span-7 border border-rule-warm p-6 max-[1080px]:col-span-full">
                        <h2 class="mb-3 text-h3 font-semibold text-ink">Before you write to us</h2>
                        <p class="legal-measure mb-3 text-ink">
                            Please do not send instructions, identity documents, beneficiary names or asset detail
                            by email or message. Email and WhatsApp are not secure channels for matter detail.
                            Everything of that kind is exchanged inside your secure account.
                        </p>
                        <p class="legal-measure text-ink">
                            We will never ask you for a password, a card number, a seed phrase or a private key.
                        </p>
                    </div>

                    <div class="col-span-4 col-start-9 max-[1080px]:col-span-full max-[1080px]:col-start-1">
                        <div class="eyebrow mb-3">Email</div>
                        <a :href="`mailto:${s['contact.email']}`" class="tap mb-6 block font-mono text-body text-ink underline decoration-gold underline-offset-4">
                            {{ s['contact.email'] }}
                        </a>

                        <div class="eyebrow mb-3">WhatsApp</div>
                        <a :href="`https://wa.me/${waNumber}`" class="tabular tap mb-6 block font-mono text-body text-ink underline decoration-gold underline-offset-4">
                            {{ s['contact.whatsapp_number'] }}
                        </a>

                        <div class="eyebrow mb-3">Working hours</div>
                        <p class="mb-2 text-body-s leading-[1.6] text-ink-70">{{ s['contact.working_hours'] }}</p>
                        <p class="help">{{ s['contact.out_of_hours_message'] }}</p>

                        <div class="mt-8 border-t border-rule-warm pt-4">
                            <div class="text-body-s font-medium text-ink">{{ s['contact.registered_entity'] }}</div>
                            <div class="text-caption text-slate">Trade Licence No. {{ s['branding.trade_licence'] }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </PublicLayout>
</template>
