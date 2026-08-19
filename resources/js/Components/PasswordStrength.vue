<script setup>
/**
 * Honest password strength.
 *
 * Scored on ESTIMATED ENTROPY, not on whether it contains a capital and a
 * symbol. "Password1!" satisfies every checklist and is worthless; a long
 * ordinary phrase satisfies none of them and is strong. The meter says what it
 * actually thinks and does not congratulate a bad password.
 */
import { computed } from 'vue';

const props = defineProps({ password: { type: String, default: '' } });

const COMMON = ['password', 'qwerty', '123456', 'letmein', 'welcome', 'admin', 'iloveyou', 'summit', 'dubai'];

const bits = computed(() => {
    const pw = props.password ?? '';
    if (!pw) return 0;

    let pool = 0;
    if (/[a-z]/.test(pw)) pool += 26;
    if (/[A-Z]/.test(pw)) pool += 26;
    if (/[0-9]/.test(pw)) pool += 10;
    if (/[^a-zA-Z0-9]/.test(pw)) pool += 33;

    let entropy = pw.length * Math.log2(pool || 1);

    // Repetition and sequences add far less than their length suggests.
    const unique = new Set(pw.toLowerCase()).size;
    if (unique < pw.length / 2) entropy *= 0.6;

    // A dictionary word inside it is the first thing an attacker tries.
    if (COMMON.some((w) => pw.toLowerCase().includes(w))) entropy *= 0.4;

    return Math.round(entropy);
});

const level = computed(() => {
    const b = bits.value;
    if (!props.password) return { label: '', tone: 'neutral', width: 0 };
    if (b < 40) return { label: 'Too weak — this would not take long to guess', tone: 'critical', width: 25 };
    if (b < 60) return { label: 'Weak — longer would help more than adding symbols', tone: 'attention', width: 50 };
    if (b < 80) return { label: 'Reasonable', tone: 'progress', width: 75 };
    return { label: 'Strong', tone: 'positive', width: 100 };
});

const barColour = { critical: 'bg-critical', attention: 'bg-attention', progress: 'bg-progress', positive: 'bg-positive', neutral: 'bg-steel' };
const textColour = { critical: 'text-critical', attention: 'text-attention', progress: 'text-progress', positive: 'text-positive', neutral: 'text-slate' };
</script>

<template>
    <div v-if="password" class="mt-2">
        <div class="h-1 w-full overflow-hidden rounded-pill bg-neutral-bg">
            <div class="h-full rounded-pill transition-[width] duration-200" :class="barColour[level.tone]" :style="{ width: `${level.width}%` }"></div>
        </div>
        <p class="mt-1.5 text-caption" :class="textColour[level.tone]" aria-live="polite">
            {{ level.label }}
            <span class="tabular text-slate">· about {{ bits }} bits</span>
        </p>
    </div>
</template>
