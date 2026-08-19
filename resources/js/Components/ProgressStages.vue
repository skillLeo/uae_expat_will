<script setup>
/**
 * Progress as NAMED STAGES.
 *
 * There is deliberately no "3 of 16" here and there never will be. The number of
 * questions depends on the answers, so a count would be a promise the engine
 * cannot keep — and the client has forbidden it. The bar shows position and
 * momentum instead.
 */
defineProps({ progress: { type: Object, required: true } });
</script>

<template>
    <div class="border-t border-ink-line">
        <div class="mx-auto max-w-[1216px] px-8 py-2.5 max-[719px]:px-4">
            <div class="mb-2 flex items-baseline justify-between gap-4">
                <span class="eyebrow">{{ progress.current_stage_label }}</span>
            </div>

            <!-- Wide: named stages. -->
            <ol class="flex flex-wrap gap-x-4 gap-y-1 max-[719px]:hidden" aria-label="Progress">
                <li
                    v-for="stage in progress.stages" :key="stage.key"
                    class="flex items-center gap-1.5 text-caption"
                    :class="{
                        'text-gold-soft': stage.state === 'current',
                        'text-steel': stage.state === 'done',
                        'text-slate': stage.state === 'upcoming',
                    }"
                    :aria-current="stage.state === 'current' ? 'step' : undefined"
                >
                    <svg
                        v-if="stage.state === 'done'" width="12" height="12" viewBox="0 0 16 16"
                        fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"
                    >
                        <polyline points="3.6,8.4 6.4,11.2 12.4,4.8" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                    <span v-else class="h-1.5 w-1.5 rounded-pill" :class="stage.state === 'current' ? 'bg-gold' : 'bg-slate'"></span>
                    {{ stage.label }}
                </li>
            </ol>

            <!-- Narrow: a single growing rule. -->
            <div class="hidden h-1 w-full overflow-hidden rounded-pill bg-ink-line max-[719px]:block">
                <div
                    class="h-full rounded-pill bg-gold transition-[width] duration-300"
                    :style="{ width: `${progress.percent}%` }"
                    role="progressbar"
                    :aria-valuenow="progress.percent"
                    aria-valuemin="0" aria-valuemax="100"
                    :aria-label="`Progress: ${progress.current_stage_label}`"
                ></div>
            </div>
        </div>
    </div>
</template>
