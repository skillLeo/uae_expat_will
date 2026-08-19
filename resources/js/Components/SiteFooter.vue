<script setup>
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import Wordmark from './Wordmark.vue';

const page = usePage();
const s = computed(() => page.props.settings ?? {});
const year = new Date().getFullYear();

const pages = [
    ['How It Works', '/how-it-works'],
    ['Do You Need a Will?', '/do-you-need-a-uae-will'],
    ['UAE Will Options', '/uae-will-registration-options'],
    ['Pricing', '/pricing'],
    ['FAQs', '/faqs'],
    ['About Us', '/about-us'],
    ['Contact', '/contact'],
];

// UX-01: the design links Terms.dc.html, which does not exist in the design
// project. These point at the real pages instead.
const legal = [
    ['Terms and Conditions', '/terms-and-conditions'],
    ['Privacy Policy', '/privacy-policy'],
    ['Payment and Refund Policy', '/payment-and-refund-policy'],
    ['Legal Disclaimer', '/legal-disclaimer'],
    ['Cookie Policy', '/cookie-policy'],
];

const openCookieSettings = () => window.dispatchEvent(new CustomEvent('cookie-settings:open'));
</script>

<template>
    <footer class="on-ink bg-ink-deep pb-12 pt-16">
        <div class="mx-auto grid max-w-[1280px] grid-cols-12 gap-8 px-8 max-[719px]:px-4">
            <div class="col-span-3 max-[1080px]:col-span-full">
                <Wordmark context="lockup" ground="ink" />
                <p class="mt-6 text-legal leading-[1.65] text-steel">
                    UAE Expat Wills is a digital legal-service platform for UAE Will assessment,
                    preparation, human legal review and registration assistance.
                </p>
            </div>

            <div class="col-span-3 grid content-start gap-2.5 max-[1080px]:col-span-full">
                <div class="eyebrow mb-1">Pages</div>
                <Link v-for="[label, href] in pages" :key="href" :href="href" class="text-body-s text-paper hover:text-gold-soft">{{ label }}</Link>
            </div>

            <div class="col-span-3 grid content-start gap-2.5 max-[1080px]:col-span-full">
                <div class="eyebrow mb-1">Legal</div>
                <Link v-for="[label, href] in legal" :key="href" :href="href" class="text-body-s text-paper hover:text-gold-soft">{{ label }}</Link>
                <button type="button" class="text-left text-body-s text-paper hover:text-gold-soft" @click="openCookieSettings">
                    Cookie Settings
                </button>
            </div>

            <div class="col-span-3 grid content-start gap-2.5 max-[1080px]:col-span-full">
                <div class="eyebrow mb-1">Contact</div>
                <!-- Live text links only. There is no contact form anywhere. -->
                <a :href="`mailto:${s['contact.email']}`" class="font-mono text-caption text-paper hover:text-gold-soft">{{ s['contact.email'] }}</a>
                <a
                    :href="`https://wa.me/${String(s['contact.whatsapp_number'] || '').replace(/[^0-9]/g, '')}`"
                    class="tabular font-mono text-caption text-paper hover:text-gold-soft"
                >{{ s['contact.whatsapp_number'] }}</a>
                <div class="text-caption text-steel">{{ s['contact.working_hours'] }}</div>
            </div>

            <div class="col-span-full my-6 h-px bg-ink-line"></div>

            <div class="col-span-9 max-[1080px]:col-span-full">
                <div class="mb-3 text-body-s font-medium text-paper">{{ s['branding.ownership_line'] }}</div>
                <p class="mb-4 max-w-[80ch] text-caption leading-[1.72] text-steel">
                    The information on this website is provided for general informational purposes and does not
                    constitute legal, tax or financial advice. Any assessment result or pathway indication is
                    preliminary. No legal or professional engagement begins until the matter is accepted by Summit
                    Legal Consultancy UAE and the applicable engagement terms are agreed. Registration is completed
                    by the competent authority, not by the platform, and no registration, enforceability, processing
                    time or outcome is guaranteed. UAE Expat Wills and Summit Legal Consultancy UAE are not a court,
                    registry, notary or government authority.
                </p>
                <div class="text-caption text-steel">
                    © {{ year }} UAE Expat Wills. A platform owned and operated by Summit Legal Consultancy UAE.
                    All rights reserved.
                </div>
            </div>
        </div>
    </footer>
</template>
