<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    links: { type: Array, required: true }, // [{ label, href, icon, active }]
});

// Correspondance provisoire FA -> RI : le temps que chaque page migre ses propres
// navLinks (icônes encore déclarées en classes Font Awesome, ex. "fa-tools"), ce
// composant partagé traduit à la volée pour rester visuellement cohérent avec le
// thème Materio partout où il est utilisé — voir maintenance-equipement-materio-migration.
const FA_TO_RI = {
    'fa-tachometer-alt': 'ri-dashboard-line',
    'fa-industry': 'ri-building-4-line',
    'fa-truck': 'ri-truck-line',
    'fa-desktop': 'ri-computer-line',
    'fa-tools': 'ri-tools-fill',
    'fa-calendar-check': 'ri-calendar-check-line',
    'fa-boxes': 'ri-archive-2-line',
};

function faToRi(icon) {
    return FA_TO_RI[icon] ?? icon;
}
</script>

<template>
    <div class="materio-item d-flex flex-wrap gap-2">
        <Link
            v-for="l in links"
            :key="l.label"
            :href="l.href"
            class="materio-item btn btn-sm"
            :class="l.active ? 'materio-item btn-primary' : 'materio-item btn-outline-primary'"
        >
            <i class="materio-item icon-base ri icon-16px me-1" :class="faToRi(l.icon)"></i>{{ l.label }}
        </Link>
    </div>
</template>
