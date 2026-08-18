<script setup>
defineProps({
    value: {
        type: String,
    },
});
</script>

<template>
    <label class="sb2-item small font-weight-bold text-gray-700 mb-1">
        <span v-if="value">{{ value }}</span>
        <span v-else><slot /></span>
    </label>
</template>
