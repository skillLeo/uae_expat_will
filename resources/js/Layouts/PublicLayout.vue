<script setup>
import { computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import SiteHeader from '@/Components/SiteHeader.vue';
import SiteFooter from '@/Components/SiteFooter.vue';
import CookieConsent from '@/Components/CookieConsent.vue';
import WhatsAppButton from '@/Components/WhatsAppButton.vue';

const props = defineProps({
    title: { type: String, default: null },
    description: { type: String, default: null },
    canonical: { type: String, default: null },
    active: { type: String, default: '' },
    structuredData: { type: [Object, Array], default: null },
});

/**
 * The JSON-LD, as text for the script tag.
 *
 * This used to be bound with v-html, which looked right and produced nothing:
 * Inertia's <Head> serialises a vnode's props into attributes, so v-html
 * became innerHTML="{...}" on an otherwise EMPTY <script> tag. Browsers and
 * crawlers both ignore that, so every page shipped its Organization, Service
 * and BreadcrumbList markup in a form Google could not read. Nothing errored
 * and the payload was visibly present in the HTML, which is why it stood.
 *
 * A text child is rendered verbatim by Head's serialiser, so the JSON has to
 * be safe as raw script content: every "<" is escaped, which stops a closing
 * script tag appearing inside any string value and ending the element early.
 * JSON parsers treat the escape as the same character, so nothing else cares.
 */
const structuredDataJson = computed(() =>
    props.structuredData
        ? JSON.stringify(props.structuredData).replace(/</g, '\\u003C')
        : null
);
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
        <component v-if="structuredDataJson" :is="'script'" type="application/ld+json">{{ structuredDataJson }}</component>
    </Head>

    <SiteHeader :active="active" />

    <main id="main">
        <slot />
    </main>

    <SiteFooter />
    <WhatsAppButton />
    <CookieConsent />
</template>
