<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps({ section: { type: Object, default: () => ({}) } });
const cfg = computed(() => props.section.settings ?? {});
</script>

<template>
    <section class="on-ink bg-ink py-24 max-[719px]:py-12">
        <div class="mx-auto max-w-[1280px] px-8 max-[719px]:px-4">
            <div class="grid grid-cols-12 gap-8">
                <div class="col-span-7 max-[1080px]:col-span-full">
                    <div class="rule-open mb-6"></div>
                    <h2 class="mb-5 max-w-[20ch] font-display text-display-l text-paper">{{ section.heading }}</h2>
                    <p class="mb-8 max-w-[58ch] text-body-l leading-[1.65] text-steel">{{ section.body }}</p>
                    <div class="flex flex-wrap items-center gap-6">
                        <Link
                            v-if="cfg.primary" :href="cfg.primary.href"
                            class="btn btn-lg border-paper bg-paper text-ink hover:bg-surface"
                        >{{ cfg.primary.label }}</Link>
                        <Link
                            v-if="cfg.secondary" :href="cfg.secondary.href"
                            class="text-legal font-medium text-paper hover:text-gold-soft"
                        >{{ cfg.secondary.label }} →</Link>
                    </div>
                </div>
                <p v-if="cfg.aside" class="col-span-4 col-start-9 self-start text-legal leading-[1.72] text-steel max-[1080px]:col-span-full max-[1080px]:col-start-1">
                    {{ cfg.aside }}
                </p>
            </div>
        </div>
    </section>
</template>
