<script setup>
/**
 * The margin-column page header.
 *
 * This is direction 1b's construction reused as page geography: the eyebrow and
 * breadcrumb sit in the margin where a clause number sits, separated from the
 * heading by the gold margin rule. Below 719px the margin column collapses and
 * its annotation is hidden rather than wrapped.
 */
defineProps({
    eyebrow: { type: String, default: null },
    breadcrumb: { type: String, default: null },
    heading: { type: String, required: true },
    lede: { type: String, default: null },
});
</script>

<template>
    <section class="bg-page pb-16 pt-[72px] max-[719px]:py-12">
        <div class="mx-auto max-w-[1280px] px-8 max-[719px]:px-4">
            <div class="margin-col">
                <div class="margin-annot">
                    <div class="eyebrow leading-[1.7]">{{ eyebrow }}</div>
                    <div v-if="breadcrumb" class="mt-3 font-mono text-[11px] leading-[1.7] text-slate">{{ breadcrumb }}</div>
                </div>
                <div class="margin-rule"></div>
                <div class="margin-body">
                    <h1 class="mb-5 max-w-[26ch] font-display text-display-l text-ink">{{ heading }}</h1>
                    <p v-if="lede" class="max-w-[62ch] text-body-l leading-[1.65] text-ink-70">{{ lede }}</p>
                    <slot />
                </div>
            </div>
        </div>
    </section>
</template>
