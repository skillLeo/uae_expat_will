<script setup>
/**
 * A data table that becomes labelled stacked cards below 900px.
 *
 * There is NO horizontal scroll anywhere in this product — a written client
 * rule — so the narrow rendering is a real card list with its own labels, not
 * the same table in a scroll box.
 */
defineProps({
    columns: { type: Array, required: true }, // [{ key, label, numeric }]
    rows: { type: Array, required: true },
    rowKey: { type: String, default: 'id' },
});
</script>

<template>
    <!-- Wide -->
    <div class="card overflow-hidden max-[900px]:hidden">
        <table class="data-table">
            <thead>
                <tr>
                    <th v-for="col in columns" :key="col.key" scope="col" :class="col.numeric ? 'text-right' : ''">
                        {{ col.label }}
                    </th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="row in rows" :key="row[rowKey]">
                    <td v-for="col in columns" :key="col.key" :class="col.numeric ? 'tabular text-right' : ''">
                        <slot :name="`cell-${col.key}`" :row="row">{{ row[col.key] }}</slot>
                    </td>
                </tr>
            </tbody>
        </table>
        <p v-if="!rows.length" class="p-6 text-center text-body-s text-slate">Nothing to show.</p>
    </div>

    <!-- Narrow: labelled stacked cards -->
    <div class="hidden grid gap-2 max-[900px]:grid">
        <article v-for="row in rows" :key="row[rowKey]" class="card p-4">
            <dl class="grid gap-2">
                <div v-for="col in columns" :key="col.key" class="grid grid-cols-[minmax(96px,40%)_minmax(0,1fr)] items-baseline gap-3">
                    <dt class="text-caption text-slate">{{ col.label }}</dt>
                    <dd :class="col.numeric ? 'tabular font-mono text-body-s' : 'text-body-s'">
                        <slot :name="`cell-${col.key}`" :row="row">{{ row[col.key] }}</slot>
                    </dd>
                </div>
            </dl>
        </article>
        <p v-if="!rows.length" class="card p-6 text-center text-body-s text-slate">Nothing to show.</p>
    </div>
</template>
