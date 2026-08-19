<script setup>
/**
 * A sheet: a centred modal at width, a bottom sheet below 768px.
 *
 * Menus, filters and pickers open in one of these rather than swapping the
 * page, which is most of what separates an app from a shrunken website. Focus
 * is trapped, Escape closes, and the body is locked while it is open.
 */
import { ref, watch, nextTick, onUnmounted } from 'vue';

const props = defineProps({
    open: { type: Boolean, default: false },
    title: { type: String, default: '' },
    size: { type: String, default: 'md' }, // sm | md | lg
});

const emit = defineEmits(['close']);
const panel = ref(null);

const widths = { sm: 'max-w-[420px]', md: 'max-w-[640px]', lg: 'max-w-[900px]' };

function onKeydown(e) {
    if (e.key === 'Escape') return emit('close');
    if (e.key !== 'Tab' || !panel.value) return;

    const focusable = panel.value.querySelectorAll(
        'a[href], button:not([disabled]), input:not([disabled]), select, textarea, [tabindex]:not([tabindex="-1"])',
    );
    if (!focusable.length) return;

    const first = focusable[0];
    const last = focusable[focusable.length - 1];

    if (e.shiftKey && document.activeElement === first) {
        e.preventDefault();
        last.focus();
    } else if (!e.shiftKey && document.activeElement === last) {
        e.preventDefault();
        first.focus();
    }
}

watch(() => props.open, async (isOpen) => {
    if (typeof document === 'undefined') return;
    document.body.style.overflow = isOpen ? 'hidden' : '';
    if (isOpen) {
        await nextTick();
        panel.value?.querySelector('input, select, textarea, button')?.focus();
    }
});

onUnmounted(() => {
    if (typeof document !== 'undefined') document.body.style.overflow = '';
});
</script>

<template>
    <Teleport to="body">
        <div
            v-if="open"
            class="z-sheet fixed inset-0 flex items-center justify-center bg-ink-deep/50 p-6 max-[767px]:items-end max-[767px]:p-0"
            @click.self="emit('close')"
            @keydown="onKeydown"
        >
            <div
                ref="panel"
                role="dialog" aria-modal="true" :aria-label="title"
                class="safe-bottom max-h-[85dvh] w-full overflow-y-auto rounded-md bg-surface shadow-sheet max-[767px]:max-h-[92dvh] max-[767px]:rounded-b-none"
                :class="widths[size]"
            >
                <header class="sticky top-0 flex items-center justify-between gap-4 border-b border-rule-cool bg-surface px-6 py-4 max-[767px]:px-4">
                    <h2 class="text-h4 font-semibold text-ink">{{ title }}</h2>
                    <button type="button" class="tap -mr-2 grid place-items-center px-2 text-slate hover:text-ink" @click="emit('close')">
                        <span class="sr-only">Close</span>
                        <svg width="20" height="20" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true">
                            <path d="M5 5l10 10M15 5L5 15" stroke-linecap="round" />
                        </svg>
                    </button>
                </header>
                <div class="p-6 max-[767px]:p-4"><slot /></div>
                <footer v-if="$slots.actions" class="safe-bottom sticky bottom-0 flex flex-wrap gap-3 border-t border-rule-cool bg-surface px-6 py-4 max-[767px]:px-4">
                    <slot name="actions" />
                </footer>
            </div>
        </div>
    </Teleport>
</template>
