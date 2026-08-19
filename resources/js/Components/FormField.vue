<script setup>
/**
 * A labelled field with reserved error space.
 *
 * The helper slot is always rendered at a fixed minimum height so an appearing
 * error never reflows the form under the user's cursor.
 */
defineProps({
    label: { type: String, required: true },
    id: { type: String, required: true },
    error: { type: String, default: null },
    help: { type: String, default: null },
    required: { type: Boolean, default: false },
});
</script>

<template>
    <div class="mb-4">
        <label class="label" :for="id">
            {{ label }}
            <span v-if="required" class="text-critical" aria-hidden="true">*</span>
        </label>
        <slot :id="id" :invalid="!!error" />
        <div class="field-slot pt-1.5">
            <p v-if="error" class="error" role="alert">{{ error }}</p>
            <p v-else-if="help" class="help">{{ help }}</p>
        </div>
    </div>
</template>
