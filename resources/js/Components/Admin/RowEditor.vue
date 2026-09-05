<script setup>
/**
 * Edits a list-of-objects setting as labelled rows.
 *
 * The alternative was the raw JSON textarea, which meant Summit could not
 * change their own authority-charge table without hand-writing JSON — one
 * missing comma refused the entire save. So they asked us to change prices
 * instead, and those requests travelled through screenshots and WhatsApp.
 *
 * Every column is free text on purpose. The charge column has to be able to
 * hold "AED 950.00", "≈ AED 2,100.00" and "Varies by Will type", because all
 * three are honest descriptions of what an authority actually charges. A
 * numeric field would make the truth unrepresentable.
 */
import { computed } from 'vue';

const props = defineProps({
    modelValue: { type: [Array, String, Object], default: () => [] },
    schema: { type: Object, required: true },
    blankRow: { type: Object, default: () => ({}) },
});

const emit = defineEmits(['update:modelValue']);

// The parent holds JSON settings as a string for the textarea path. Accept
// either shape so this works whichever way it is handed over.
const rows = computed(() => {
    const v = props.modelValue;
    if (Array.isArray(v)) return v;
    if (typeof v === 'string' && v.trim() !== '') {
        try {
            const parsed = JSON.parse(v);
            return Array.isArray(parsed) ? parsed : [];
        } catch {
            return [];
        }
    }
    return [];
});

const columns = computed(() => props.schema.columns ?? []);
const gridTemplate = computed(
    () => columns.value.map((c) => c.width || '1fr').join(' ') + ' auto'
);

const commit = (next) => emit('update:modelValue', next);

function setCell(index, key, value) {
    const next = rows.value.map((row, i) => (i === index ? { ...row, [key]: value } : row));
    commit(next);
}

function addRow() {
    commit([...rows.value, { ...props.blankRow }]);
}

function removeRow(index) {
    commit(rows.value.filter((_, i) => i !== index));
}

function move(index, by) {
    const target = index + by;
    if (target < 0 || target >= rows.value.length) return;

    const next = [...rows.value];
    [next[index], next[target]] = [next[target], next[index]];
    commit(next);
}
</script>

<template>
    <div class="grid gap-3">
        <p v-if="!rows.length" class="help">{{ schema.empty }}</p>

        <div
            v-for="(row, index) in rows" :key="index"
            class="card-paper grid gap-3 border border-rule-warm p-3"
            :style="{ gridTemplateColumns: gridTemplate }"
        >
            <div v-for="col in columns" :key="col.key" class="min-w-0">
                <label
                    :for="`row-${index}-${col.key}`"
                    class="mb-1 block text-caption font-medium uppercase tracking-[0.06em] text-slate"
                >{{ col.label }}</label>

                <textarea
                    v-if="col.type === 'textarea'"
                    :id="`row-${index}-${col.key}`"
                    class="field min-h-[64px] text-body-s"
                    :placeholder="col.placeholder"
                    :value="row[col.key] ?? ''"
                    @input="setCell(index, col.key, $event.target.value)"
                ></textarea>

                <input
                    v-else
                    :id="`row-${index}-${col.key}`"
                    class="field text-body-s"
                    :placeholder="col.placeholder"
                    :value="row[col.key] ?? ''"
                    @input="setCell(index, col.key, $event.target.value)"
                >

                <p v-if="col.help" class="help mt-1">{{ col.help }}</p>
            </div>

            <div class="flex flex-none items-start gap-1 pt-5">
                <button
                    type="button" class="tap rounded-sm px-2 py-1 text-body-s text-ink-70 hover:bg-paper disabled:opacity-30"
                    :disabled="index === 0" :aria-label="`Move row ${index + 1} up`"
                    @click="move(index, -1)"
                >↑</button>
                <button
                    type="button" class="tap rounded-sm px-2 py-1 text-body-s text-ink-70 hover:bg-paper disabled:opacity-30"
                    :disabled="index === rows.length - 1" :aria-label="`Move row ${index + 1} down`"
                    @click="move(index, 1)"
                >↓</button>
                <button
                    type="button" class="tap rounded-sm px-2 py-1 text-body-s text-critical hover:bg-critical-bg"
                    :aria-label="`Remove row ${index + 1}`"
                    @click="removeRow(index)"
                >Remove</button>
            </div>
        </div>

        <div>
            <button type="button" class="btn btn-sm btn-secondary" @click="addRow">
                {{ schema.add_label }}
            </button>
        </div>
    </div>
</template>
