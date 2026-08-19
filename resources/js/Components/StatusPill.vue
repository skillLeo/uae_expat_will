<script setup>
/**
 * A status pill.
 *
 * Every pill carries an ICON AND A TEXT LABEL as well as a colour, because no
 * legal meaning in this product may be communicated by colour alone. It never
 * wraps: a status that breaks across two lines stops reading as one token.
 */
import { computed } from 'vue';

const props = defineProps({
    tone: { type: String, default: 'neutral' },
    label: { type: String, required: true },
});

// Each tone gets a visually distinct glyph, so the six states remain
// distinguishable in greyscale and to a colour-blind reader.
const glyph = computed(() => ({
    positive: 'M3.6 8.4 6.4 11.2 12.4 4.8',              // tick
    progress: 'M8 3.4v4.6l3 1.8',                         // clock hand
    attention: 'M8 4v5M8 11.4v.2',                        // exclamation
    held: 'M5.6 7.2V5.6a2.4 2.4 0 0 1 4.8 0v1.6',         // padlock shackle
    critical: 'M5 5l6 6M11 5l-6 6',                       // cross
    neutral: 'M4.6 8h6.8',                                // dash
}[props.tone] ?? 'M4.6 8h6.8'));
</script>

<template>
    <span class="pill" :class="`pill-${tone}`">
        <svg
            class="flex-none"
            width="13" height="13" viewBox="0 0 16 16"
            fill="none" stroke="currentColor" stroke-width="1.7"
            stroke-linecap="round" stroke-linejoin="round"
            aria-hidden="true"
        >
            <circle v-if="tone !== 'held'" cx="8" cy="8" r="6.2" stroke-width="1.3" />
            <rect v-else x="3.6" y="7.2" width="8.8" height="6.4" rx="1" stroke-width="1.5" />
            <path :d="glyph" />
        </svg>
        <span>{{ label }}</span>
    </span>
</template>
