<script setup>
import { computed } from 'vue';

const props = defineProps({
    // [{ label, value, color }]
    bars: { type: Array, required: true },
    unit: { type: String, default: '' },
});

const max = computed(() => Math.max(...props.bars.map((b) => b.value), 1));

function formatValue(v) {
    return `${Number(v).toLocaleString('fr-FR')}${props.unit}`;
}
</script>

<template>
    <div class="space-y-3">
        <div v-for="bar in bars" :key="bar.label">
            <div class="mb-1 flex items-center justify-between text-xs">
                <span class="font-medium text-gray-600 dark:text-gray-300">{{ bar.label }}</span>
                <span class="text-gray-500 dark:text-gray-400">{{ formatValue(bar.value) }}</span>
            </div>
            <div class="h-3 w-full overflow-hidden rounded-full bg-gray-100 dark:bg-gray-800">
                <div
                    class="h-full rounded-full transition-all"
                    :style="{ width: `${(bar.value / max) * 100}%`, backgroundColor: bar.color }"
                ></div>
            </div>
        </div>
    </div>
</template>
