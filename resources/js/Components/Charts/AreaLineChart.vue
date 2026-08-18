<script setup>
import { computed, ref } from 'vue';

const props = defineProps({
    labels: { type: Array, required: true },
    values: { type: Array, required: true },
    color: { type: String, default: '#2a78d6' },
    unit: { type: String, default: '' },
});

const width = 640;
const height = 220;
const padLeft = 44;
const padRight = 16;
const padTop = 16;
const padBottom = 28;

const plotW = width - padLeft - padRight;
const plotH = height - padTop - padBottom;

const maxValue = computed(() => {
    const max = Math.max(...props.values, 0);
    return max === 0 ? 10 : Math.ceil(max * 1.15);
});

const yTicks = computed(() => {
    const steps = 4;
    return Array.from({ length: steps + 1 }, (_, i) => Math.round((maxValue.value / steps) * i));
});

function xFor(i) {
    if (props.values.length <= 1) return padLeft;
    return padLeft + (i / (props.values.length - 1)) * plotW;
}

function yFor(v) {
    return padTop + plotH - (v / maxValue.value) * plotH;
}

const linePath = computed(() =>
    props.values.map((v, i) => `${i === 0 ? 'M' : 'L'} ${xFor(i)} ${yFor(v)}`).join(' ')
);

const areaPath = computed(() => {
    if (props.values.length === 0) return '';
    const last = props.values.length - 1;
    return `${linePath.value} L ${xFor(last)} ${padTop + plotH} L ${xFor(0)} ${padTop + plotH} Z`;
});

const hoverIndex = ref(null);

function onMove(event) {
    const svg = event.currentTarget;
    const rect = svg.getBoundingClientRect();
    const relX = ((event.clientX - rect.left) / rect.width) * width;
    if (props.values.length <= 1) {
        hoverIndex.value = 0;
        return;
    }
    const ratio = Math.min(Math.max((relX - padLeft) / plotW, 0), 1);
    hoverIndex.value = Math.round(ratio * (props.values.length - 1));
}

function onLeave() {
    hoverIndex.value = null;
}

function formatValue(v) {
    return `${Number(v).toLocaleString('fr-FR')}${props.unit}`;
}
</script>

<template>
    <div class="relative">
        <svg
            :viewBox="`0 0 ${width} ${height}`"
            class="w-full"
            style="color-scheme: light dark"
            @mousemove="onMove"
            @mouseleave="onLeave"
        >
            <!-- Gridlines -->
            <g>
                <line
                    v-for="tick in yTicks"
                    :key="tick"
                    :x1="padLeft"
                    :x2="width - padRight"
                    :y1="yFor(tick)"
                    :y2="yFor(tick)"
                    stroke="currentColor"
                    class="text-gray-200 dark:text-gray-800"
                    stroke-width="1"
                />
                <text
                    v-for="tick in yTicks"
                    :key="`label-${tick}`"
                    :x="padLeft - 8"
                    :y="yFor(tick) + 3"
                    text-anchor="end"
                    class="fill-gray-400 text-[10px] dark:fill-gray-500"
                >
                    {{ tick.toLocaleString('fr-FR') }}
                </text>
            </g>

            <!-- Aire -->
            <path :d="areaPath" :fill="color" opacity="0.1" />
            <!-- Ligne -->
            <path :d="linePath" fill="none" :stroke="color" stroke-width="2" stroke-linejoin="round" stroke-linecap="round" />

            <!-- Points de fin -->
            <circle
                v-if="values.length"
                :cx="xFor(values.length - 1)"
                :cy="yFor(values[values.length - 1])"
                r="4"
                :fill="color"
                stroke="white"
                stroke-width="2"
                class="dark:stroke-gray-900"
            />

            <!-- Crosshair -->
            <g v-if="hoverIndex !== null">
                <line
                    :x1="xFor(hoverIndex)"
                    :x2="xFor(hoverIndex)"
                    :y1="padTop"
                    :y2="padTop + plotH"
                    stroke="currentColor"
                    class="text-gray-300 dark:text-gray-700"
                    stroke-width="1"
                />
                <circle :cx="xFor(hoverIndex)" :cy="yFor(values[hoverIndex])" r="5" :fill="color" stroke="white" stroke-width="2" class="dark:stroke-gray-900" />
            </g>

            <!-- Labels X -->
            <text
                v-for="(label, i) in labels"
                :key="label"
                :x="xFor(i)"
                :y="height - 6"
                text-anchor="middle"
                class="fill-gray-400 text-[10px] dark:fill-gray-500"
            >
                {{ label }}
            </text>
        </svg>

        <div
            v-if="hoverIndex !== null"
            class="pointer-events-none absolute top-2 rounded-md border border-gray-200 bg-white px-2 py-1 text-xs shadow-md dark:border-gray-700 dark:bg-gray-800"
            :style="{ left: `${(xFor(hoverIndex) / width) * 100}%`, transform: 'translateX(-50%)' }"
        >
            <p class="font-medium text-gray-800 dark:text-gray-100">{{ formatValue(values[hoverIndex]) }}</p>
            <p class="text-gray-400">{{ labels[hoverIndex] }}</p>
        </div>
    </div>
</template>
