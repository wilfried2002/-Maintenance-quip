<script setup>
import { Head } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InterventionManager from '@/Components/Modules/InterventionManager.vue';
import ModuleNavTiles from '@/Components/ModuleNavTiles.vue';
import { themes } from '@/moduleTheme';

defineProps({
    interventions: { type: Array, required: true },
    equipements: { type: Array, required: true },
    techniciens: { type: Array, required: true },
    pieces: { type: Array, required: true },
});

const t = themes.orange;

const navLinks = [
    { label: 'Tableau de bord', href: route('equipements-industriels.dashboard'), icon: 'fa-tachometer-alt', active: false },
    { label: 'Équipements', href: route('equipements-industriels.index'), icon: 'fa-industry', active: false },
    { label: 'Interventions', href: route('equipements-industriels.interventions.index'), icon: 'fa-tools', active: true },
    { label: 'Plans préventifs', href: route('equipements-industriels.plans.index'), icon: 'fa-calendar-check', active: false },
    { label: 'Pièces', href: route('equipements-industriels.pieces.index'), icon: 'fa-boxes', active: false },
];
</script>

<template>
    <Head title="Interventions — Équipements industriels" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight" :class="t.accent">
                    Équipements industriels
                </h2>
                <ModuleNavTiles :links="navLinks" />
            </div>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <InterventionManager
                    theme="orange"
                    :interventions="interventions"
                    :equipements="equipements"
                    :techniciens="techniciens"
                    :pieces="pieces"
                    store-url="/equipements-industriels/interventions"
                    equipement-label-key="designation"
                    :show-form-block="true"
                    :show-table="true"
                />
            </div>
        </div>
    </AuthenticatedLayout>
</template>
