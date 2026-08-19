<script setup>
import { Head } from '@inertiajs/vue3';
import SiteHeader from '@/Components/SiteHeader.vue';
import SiteFooter from '@/Components/SiteFooter.vue';
import CookieConsent from '@/Components/CookieConsent.vue';
import WhatsAppButton from '@/Components/WhatsAppButton.vue';

defineProps({
    title: { type: String, default: null },
    description: { type: String, default: null },
    canonical: { type: String, default: null },
    active: { type: String, default: '' },
    structuredData: { type: [Object, Array], default: null },
});
</script>

<template>
    <Head>
        <title v-if="title">{{ title }}</title>
        <meta v-if="description" name="description" :content="description" />
        <!-- Self-referencing canonical on every page. -->
        <link v-if="canonical" rel="canonical" :href="canonical" />
        <meta v-if="title" property="og:title" :content="title" />
        <meta v-if="description" property="og:description" :content="description" />
        <meta property="og:type" content="website" />
        <meta v-if="canonical" property="og:url" :content="canonical" />
        <component
            v-if="structuredData" :is="'script'" type="application/ld+json"
            v-html="JSON.stringify(structuredData)"
        />
    </Head>

    <SiteHeader :active="active" />

    <main id="main">
        <slot />
    </main>

    <SiteFooter />
    <WhatsAppButton />
    <CookieConsent />
</template>
