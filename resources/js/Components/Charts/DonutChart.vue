<script setup>
import { computed } from 'vue';

const props = defineProps({
    // [{ label, value, color }] — max 4 recommandé (palette catégorielle validée)
    segments: { type: Array, required: true },
    centerLabel: { type: String, default: '' },
});

const total = computed(() => props.segments.reduce((sum, s) => sum + s.value, 0));

const radius = 70;
const circumference = 2 * Math.PI * radius;

const arcs = computed(() => {
    let cumulative = 0;
    return props.segments.map((s) => {
        const fraction = total.value > 0 ? s.value / total.value : 0;
        const length = fraction * circumference;
        const arc = {
            ...s,
            fraction,
            dasharray: `${Math.max(length - 2, 0)} ${circumference - Math.max(length - 2, 0)}`,
            dashoffset: -cumulative,
        };
        cumulative += length;
        return arc;
    });
});
</script>

<template>
    <div class="flex flex-col items-center gap-4 sm:flex-row sm:items-center sm:justify-center">
        <svg viewBox="0 0 180 180" class="h-44 w-44 shrink-0">
            <g transform="translate(90,90) rotate(-90)">
                <circle r="70" fill="none" stroke="currentColor" class="text-gray-100 dark:text-gray-800" stroke-width="24" />
                <circle
                    v-for="arc in arcs"
                    :key="arc.label"
                    r="70"
                    fill="none"
                    :stroke="arc.color"
                    stroke-width="24"
                    :stroke-dasharray="arc.dasharray"
                    :stroke-dashoffset="arc.dashoffset"
                >
                    <title>{{ arc.label }} : {{ arc.value }}</title>
                </circle>
            </g>
            <text x="90" y="86" text-anchor="middle" class="fill-gray-800 text-2xl font-semibold dark:fill-gray-100">{{ total }}</text>
            <text x="90" y="104" text-anchor="middle" class="fill-gray-400 text-[10px] dark:fill-gray-500">{{ centerLabel }}</text>
        </svg>

        <ul class="w-full space-y-2 sm:w-auto">
            <li v-for="arc in arcs" :key="arc.label" class="flex items-center gap-2 text-sm">
                <span class="size-2.5 shrink-0 rounded-full" :style="{ backgroundColor: arc.color }"></span>
                <span class="flex-1 text-gray-600 dark:text-gray-300">{{ arc.label }}</span>
                <span class="font-medium text-gray-800 dark:text-gray-100">{{ arc.value }}</span>
                <span class="w-10 text-right text-xs text-gray-400">{{ Math.round(arc.fraction * 100) }}%</span>
            </li>
        </ul>
    </div>
</template>
