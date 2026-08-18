<script setup>
import { computed } from 'vue';

const props = defineProps({
    label: { type: String, required: true },
    percent: { type: Number, required: true }, // 0-100
});

const color = computed(() => {
    if (props.percent >= 80) return '#0ca30c';
    if (props.percent >= 50) return '#fab219';
    return '#d03b3b';
});

const track = computed(() => {
    if (props.percent >= 80) return '#cdeecd';
    if (props.percent >= 50) return '#fde7c0';
    return '#f6d2d2';
});

const radius = 60;
const circumference = Math.PI * radius; // demi-cercle
const offset = computed(() => circumference - (Math.min(Math.max(props.percent, 0), 100) / 100) * circumference);
</script>

<template>
    <div class="flex flex-col items-center">
        <svg viewBox="0 0 140 80" class="w-40">
            <path d="M 10 70 A 60 60 0 0 1 130 70" fill="none" :stroke="track" stroke-width="14" stroke-linecap="round" />
            <path
                d="M 10 70 A 60 60 0 0 1 130 70"
                fill="none"
                :stroke="color"
                stroke-width="14"
                stroke-linecap="round"
                :stroke-dasharray="circumference"
                :stroke-dashoffset="offset"
            />
            <text x="70" y="62" text-anchor="middle" class="fill-gray-800 text-xl font-semibold dark:fill-gray-100">{{ Math.round(percent) }}%</text>
        </svg>
        <p class="-mt-1 text-center text-xs text-gray-500 dark:text-gray-400">{{ label }}</p>
    </div>
</template>
