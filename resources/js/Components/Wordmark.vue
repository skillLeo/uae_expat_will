<script setup>
/**
 * The swappable identity.
 *
 * Three directions × two contexts × two grounds = twelve renderings, all from
 * this one component. Switching the whole product to 1a or 1c is a single
 * settings value (`branding.wordmark_direction`) — the header, the footer, the
 * emails and every page follow, with no rebuild.
 *
 *   margin (1b, live) — UAE sits where a clause number sits: small, gold, in the
 *     margin, separated by the margin rule of a court filing. The lockup is page
 *     geography rather than a line of type, which is why the same construction
 *     becomes the page grid.
 *   engrossment (1a) — set the way a deed's title block is set, tracked open to
 *     fill a defined measure, hairline above and heavier rule below.
 *   registers (1c) — one line, two voices: the grotesque the interface speaks in
 *     and the display serif the document speaks in, meeting on a shared baseline.
 */
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

const props = defineProps({
    direction: { type: String, default: null },   // margin | engrossment | registers
    context: { type: String, default: 'header' }, // header | lockup
    ground: { type: String, default: 'paper' },   // paper | ink
});

const page = usePage();

const dir = computed(
    () => props.direction ?? page.props.settings?.['branding.wordmark_direction'] ?? 'margin',
);

const onInk = computed(() => props.ground === 'ink');
const isLockup = computed(() => props.context === 'lockup');

const shortLine = computed(
    () => page.props.settings?.['branding.short_line'] ?? 'A Summit Legal Consultancy UAE Platform',
);

// UAE takes gold-soft on ink and gold-strong on paper; the rule itself stays
// decorative gold on ink and gold-soft on paper.
const uaeColour = computed(() => (onInk.value ? 'text-gold-soft' : 'text-gold-strong'));
const nameColour = computed(() => (onInk.value ? 'text-paper' : 'text-ink'));
const ruleColour = computed(() => (onInk.value ? 'bg-gold' : 'bg-gold-soft'));
const subColour = computed(() => (onInk.value ? 'text-steel' : 'text-slate'));
</script>

<template>
    <div class="inline-block">
        <!-- 1b · The Margin -->
        <div v-if="dir === 'margin'" class="flex items-baseline">
            <div
                :class="[uaeColour, isLockup ? 'w-12 text-[14px] tracking-[0.12em]' : 'w-8 text-[10px] tracking-[0.1em]']"
                class="flex-none font-display font-medium"
            >
                UAE
            </div>
            <div class="w-px self-stretch" :class="onInk ? 'bg-gold' : 'bg-gold-soft'"></div>
            <div :class="isLockup ? 'pl-5' : 'pl-3'">
                <div
                    class="font-display leading-none tracking-[-0.015em]"
                    :class="[nameColour, isLockup ? 'text-[42px]' : 'text-[26px]']"
                >
                    Expat Wills
                </div>
                <div
                    v-if="isLockup"
                    class="pt-3 text-[10px] font-medium uppercase leading-[1.4] tracking-[0.135em]"
                    :class="subColour"
                >
                    {{ shortLine }}
                </div>
            </div>
        </div>

        <!-- 1a · The Engrossment -->
        <div v-else-if="dir === 'engrossment'" :class="isLockup ? 'w-[300px]' : 'w-[190px]'">
            <div class="h-px" :class="onInk ? 'bg-gold' : 'bg-gold-soft'"></div>
            <div
                class="text-center font-display font-medium uppercase leading-[1.2]"
                :class="[
                    nameColour,
                    isLockup
                        ? 'py-3 text-[25px] tracking-[0.155em] indent-[0.155em]'
                        : 'py-[6px] text-[16px] tracking-[0.145em] indent-[0.145em]',
                ]"
            >
                UAE Expat Wills
            </div>
            <div
                :class="[
                    isLockup ? 'h-0.5' : 'h-[1.5px]',
                    onInk ? 'bg-paper' : 'bg-ink',
                ]"
            ></div>
            <div
                v-if="isLockup"
                class="pt-2.5 text-center text-[10px] font-medium uppercase leading-[1.4] tracking-[0.135em] indent-[0.135em]"
                :class="subColour"
            >
                {{ shortLine }}
            </div>
        </div>

        <!-- 1c · The Two Registers -->
        <div v-else>
            <div class="flex items-baseline" :class="isLockup ? 'gap-3' : 'gap-[9px]'">
                <span
                    class="font-sans font-semibold"
                    :class="[
                        onInk ? 'text-steel' : 'text-ink-70',
                        isLockup ? 'text-[14px] tracking-[0.17em]' : 'text-[11px] tracking-[0.16em]',
                    ]"
                >
                    UAE
                </span>
                <span
                    class="font-display leading-none tracking-[-0.018em]"
                    :class="[nameColour, isLockup ? 'text-[42px]' : 'text-[27px]']"
                >
                    Expat Wills
                </span>
            </div>
            <div v-if="isLockup" class="flex items-center gap-3 pt-3.5">
                <div class="h-px w-11 bg-gold"></div>
                <div class="text-[10px] font-medium uppercase leading-[1.4] tracking-[0.135em]" :class="subColour">
                    {{ shortLine }}
                </div>
            </div>
        </div>
    </div>
</template>
