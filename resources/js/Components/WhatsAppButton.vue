<script setup>
/**
 * The floating WhatsApp control.
 *
 * It is SUPPRESSED while the cookie bar or an assessment action bar is on
 * screen. The three must never overlap, and the stacking order in app.css is
 * fixed; this component handles the part CSS cannot, which is knowing whether
 * the other two are currently present.
 */
import { computed, ref, onMounted, onUnmounted } from 'vue';
import { usePage } from '@inertiajs/vue3';

const page = usePage();
const blocked = ref(false);

const number = computed(
    () => String(page.props.settings?.['contact.whatsapp_number'] ?? '').replace(/[^0-9]/g, ''),
);

function check() {
    blocked.value = !!document.querySelector('.z-cookiebar, .z-actionbar');
}

let observer;
onMounted(() => {
    check();
    observer = new MutationObserver(check);
    observer.observe(document.body, { childList: true, subtree: true });
});
onUnmounted(() => observer?.disconnect());
</script>

<template>
    <a
        v-if="number && !blocked"
        :href="`https://wa.me/${number}`"
        target="_blank" rel="noopener"
        class="z-whatsapp safe-bottom fixed bottom-6 right-6 grid h-14 w-14 place-items-center rounded-pill bg-ink text-paper shadow-float transition-colors hover:bg-ink-deep max-[719px]:bottom-4 max-[719px]:right-4"
    >
        <span class="sr-only">Message us on WhatsApp</span>
        <svg width="26" height="26" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
            <path d="M12.04 2C6.58 2 2.13 6.45 2.13 11.91c0 1.75.46 3.45 1.32 4.95L2 22l5.25-1.38a9.9 9.9 0 0 0 4.79 1.22h.01c5.46 0 9.91-4.45 9.91-9.91 0-2.65-1.03-5.14-2.9-7.01A9.82 9.82 0 0 0 12.04 2Zm0 18.15h-.01a8.2 8.2 0 0 1-4.19-1.15l-.3-.18-3.11.82.83-3.04-.2-.31a8.19 8.19 0 0 1-1.26-4.38c0-4.54 3.7-8.23 8.24-8.23 2.2 0 4.27.86 5.83 2.41a8.18 8.18 0 0 1 2.41 5.83c0 4.54-3.7 8.23-8.24 8.23Zm4.52-6.16c-.25-.13-1.47-.72-1.69-.81-.23-.08-.39-.12-.56.13-.16.24-.64.8-.78.97-.15.16-.29.18-.54.06-.25-.13-1.05-.39-1.99-1.23-.74-.66-1.23-1.47-1.38-1.72-.14-.25-.01-.38.11-.5.11-.11.25-.29.37-.44.13-.15.17-.25.25-.42.08-.16.04-.31-.02-.44-.06-.12-.56-1.34-.76-1.84-.2-.48-.4-.42-.56-.43h-.47c-.16 0-.43.06-.65.31-.23.25-.86.84-.86 2.05s.88 2.38 1 2.54c.13.17 1.74 2.65 4.21 3.72.59.25 1.05.4 1.4.52.59.19 1.13.16 1.55.1.47-.07 1.47-.6 1.67-1.18.21-.58.21-1.07.15-1.18-.06-.1-.23-.16-.48-.29Z"/>
        </svg>
    </a>
</template>
