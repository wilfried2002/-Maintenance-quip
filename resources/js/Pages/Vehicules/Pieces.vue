<script setup>
import { Head } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PieceManager from '@/Components/Modules/PieceManager.vue';
import ModuleNavTiles from '@/Components/ModuleNavTiles.vue';
import { themes } from '@/moduleTheme';

defineProps({
    pieces: { type: Array, required: true },
});

const t = themes.green;

const navLinks = [
    { label: 'Tableau de bord', href: route('vehicules.dashboard'), icon: 'fa-tachometer-alt', active: false },
    { label: 'Véhicules', href: route('vehicules.index'), icon: 'fa-truck', active: false },
    { label: 'Interventions', href: route('vehicules.interventions.index'), icon: 'fa-tools', active: false },
    { label: 'Plans préventifs', href: route('vehicules.plans.index'), icon: 'fa-calendar-check', active: false },
    { label: 'Pièces', href: route('vehicules.pieces.index'), icon: 'fa-boxes', active: true },
];
</script>

<template>
    <Head title="Pièces — Parc automobile" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight" :class="t.accent">
                    Parc automobile
                </h2>
                <ModuleNavTiles :links="navLinks" />
            </div>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <PieceManager
                    theme="green"
                    :pieces="pieces"
                    store-url="/vehicules/pieces"
                    update-url-base="/vehicules/pieces"
                    destroy-url-base="/vehicules/pieces"
                    :show-form-block="true"
                    :show-table="true"
                />
            </div>
        </div>
    </AuthenticatedLayout>
</template>
