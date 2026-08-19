<script setup>
/**
 * The FAQ accordion.
 *
 * Every answer is present in the server-rendered DOM even when collapsed —
 * hidden with a CSS grid collapse rather than v-if — so the content is indexable
 * and findable with the browser's own find-in-page. Legal caveats must never be
 * locked behind an interaction.
 */
import { ref } from 'vue';

defineProps({ faqs: { type: Array, required: true } });

const open = ref(new Set());
const copied = ref(null);

function toggle(id) {
    const next = new Set(open.value);
    next.has(id) ? next.delete(id) : next.add(id);
    open.value = next;
}

function copyLink(anchor) {
    const url = `${window.location.origin}${window.location.pathname}#${anchor}`;
    navigator.clipboard?.writeText(url);
    copied.value = anchor;
    setTimeout(() => { copied.value = null; }, 2000);
}
</script>

<template>
    <div class="border-t border-rule-warm">
        <div v-for="faq in faqs" :key="faq.id" :id="faq.anchor" class="border-b border-rule-warm">
            <h3>
                <button
                    type="button"
                    class="tap flex w-full items-start justify-between gap-4 py-3.5 text-left"
                    :aria-expanded="open.has(faq.id)"
                    :aria-controls="`faq-answer-${faq.id}`"
                    @click="toggle(faq.id)"
                >
                    <span class="text-body font-semibold leading-[1.4] text-ink">{{ faq.question }}</span>
                    <svg
                        class="mt-1 flex-none transition-transform duration-200"
                        :class="{ 'rotate-180': open.has(faq.id) }"
                        width="18" height="18" viewBox="0 0 16 16" fill="none"
                        stroke="#8A6512" stroke-width="1.6" aria-hidden="true"
                    >
                        <polyline points="4,6.5 8,10.5 12,6.5" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                </button>
            </h3>

            <!-- Present in the DOM whether open or closed. -->
            <div
                :id="`faq-answer-${faq.id}`"
                class="grid transition-[grid-template-rows] duration-200"
                :style="{ gridTemplateRows: open.has(faq.id) ? '1fr' : '0fr' }"
            >
                <div class="overflow-hidden">
                    <div class="pb-4">
                        <div class="prose-measure text-legal leading-[1.72] text-ink-70" v-html="faq.answer"></div>
                        <button
                            type="button"
                            class="mt-3 text-caption font-medium text-gold-strong"
                            @click="copyLink(faq.anchor)"
                        >
                            {{ copied === faq.anchor ? 'Link copied' : 'Copy link to this answer' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
