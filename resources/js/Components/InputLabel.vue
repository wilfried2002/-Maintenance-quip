<script setup>
defineProps({
    value: {
        type: String,
    },
});
</script>

<template>
    <label class="materio-item form-label small fw-semibold text-body-secondary mb-1">
        <span v-if="value">{{ value }}</span>
        <span v-else><slot /></span>
    </label>
</template>
