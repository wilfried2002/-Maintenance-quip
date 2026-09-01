<script setup>
defineProps({
    message: {
        type: String,
    },
});
</script>

<template>
    <div v-show="message" class="materio-item invalid-feedback d-block">
        {{ message }}
    </div>
</template>
