<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps({
    label: { type: String, required: true },
    value: { type: [String, Number], required: true },
    delta: { type: String, default: null }, // texte déjà formaté, ex. "+3 vs 30j"
    deltaGood: { type: Boolean, default: true },
    icon: { type: String, default: 'ri-bar-chart-line' }, // classe Remix Icon, ex. "ri-building-4-line"
    variant: { type: String, default: 'primary' }, // primary | success | info | warning | danger
    href: { type: String, default: null }, // si renseigné, la tuile devient un lien cliquable
});

const tag = computed(() => (props.href ? Link : 'div'));

// Correspondance provisoire FA -> RI (voir ModuleNavTiles.vue, même principe) : le temps
// que chaque appelant migre son propre prop icon="fa-...".
const FA_TO_RI = {
    'fa-chart-bar': 'ri-bar-chart-line',
    'fa-cubes': 'ri-stack-line',
    'fa-industry': 'ri-building-4-line',
    'fa-truck': 'ri-truck-line',
    'fa-desktop': 'ri-computer-line',
    'fa-tools': 'ri-tools-fill',
    'fa-coins': 'ri-coins-line',
    'fa-calendar-check': 'ri-calendar-check-line',
    'fa-calendar': 'ri-calendar-line',
    'fa-boxes': 'ri-archive-2-line',
};

const riIcon = computed(() => FA_TO_RI[props.icon] ?? props.icon);
</script>

<template>
    <component
        :is="tag"
        :href="href ?? undefined"
        class="card h-100 border-start"
        :class="[`border-${variant}`, href ? 'stat-tile-link' : '']"
    >
        <div class="card-body">
            <div class="flex items-center">
                <div class="flex-1 mr-2">
                    <div class="uppercase text-sm font-semibold mb-1" :class="`text-${variant}`">
                        {{ label }}
                    </div>
                    <div class="text-2xl font-bold mb-0">{{ value }}</div>
                    <div v-if="delta" class="text-sm mt-1" :class="deltaGood ? 'text-success' : 'text-danger'">
                        {{ delta }}
                    </div>
                </div>
                <div class="flex-shrink-0">
                    <i class="icon-base ri text-gray-400 opacity-50 text-2xl" :class="riIcon"></i>
                </div>
            </div>
        </div>
    </component>
</template>
