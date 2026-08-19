<script setup>
/**
 * Reorderable list.
 *
 * Uses native HTML drag and drop for the pointer path, and up/down buttons for
 * everything else — a drag-only reorder is unusable by keyboard and unreliable
 * on touch, so the buttons are the real control and the drag is the shortcut.
 */
import { ref } from 'vue';

const props = defineProps({
    items: { type: Array, required: true },
    itemKey: { type: String, default: 'id' },
    disabled: { type: Boolean, default: false },
});

const emit = defineEmits(['reorder']);
const dragging = ref(null);

function move(from, to) {
    if (to < 0 || to >= props.items.length) return;
    const next = [...props.items];
    const [item] = next.splice(from, 1);
    next.splice(to, 0, item);
    emit('reorder', next.map((i) => i[props.itemKey]));
}

function onDrop(index) {
    if (dragging.value === null || dragging.value === index) return;
    move(dragging.value, index);
    dragging.value = null;
}
</script>

<template>
    <ul class="grid gap-2">
        <li
            v-for="(item, i) in items" :key="item[itemKey]"
            :draggable="!disabled"
            class="card flex items-start gap-3 p-4"
            :class="{ 'opacity-50': dragging === i }"
            @dragstart="dragging = i"
            @dragover.prevent
            @drop.prevent="onDrop(i)"
            @dragend="dragging = null"
        >
            <div v-if="!disabled" class="flex flex-none flex-col gap-1 pt-0.5">
                <button
                    type="button" class="grid h-6 w-6 place-items-center rounded-xs text-slate hover:bg-paper hover:text-ink disabled:opacity-30"
                    :disabled="i === 0" :aria-label="`Move up`" @click="move(i, i - 1)"
                >
                    <svg width="14" height="14" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><polyline points="4,10 8,6 12,10" stroke-linecap="round" stroke-linejoin="round" /></svg>
                </button>
                <button
                    type="button" class="grid h-6 w-6 place-items-center rounded-xs text-slate hover:bg-paper hover:text-ink disabled:opacity-30"
                    :disabled="i === items.length - 1" :aria-label="`Move down`" @click="move(i, i + 1)"
                >
                    <svg width="14" height="14" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><polyline points="4,6 8,10 12,6" stroke-linecap="round" stroke-linejoin="round" /></svg>
                </button>
            </div>
            <div class="min-w-0 flex-1"><slot :item="item" :index="i" /></div>
        </li>
    </ul>
</template>
