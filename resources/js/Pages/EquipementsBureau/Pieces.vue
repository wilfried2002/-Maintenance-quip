<script setup>
import { Head } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PieceManager from '@/Components/Modules/PieceManager.vue';
import ModuleNavTiles from '@/Components/ModuleNavTiles.vue';
import { themes } from '@/moduleTheme';

defineProps({
    pieces: { type: [Array, Object], required: true },
    fournisseurs: { type: Array, required: true },
});

const t = themes.blue;

const navLinks = [
    { label: 'Tableau de bord', href: route('equipements-bureau.dashboard'), icon: 'fa-tachometer-alt', active: false },
    { label: 'Équipements', href: route('equipements-bureau.index'), icon: 'fa-desktop', active: false },
    { label: 'Interventions', href: route('equipements-bureau.interventions.index'), icon: 'fa-tools', active: false },
    { label: 'Plans préventifs', href: route('equipements-bureau.plans.index'), icon: 'fa-calendar-check', active: false },
    { label: 'Pièces', href: route('equipements-bureau.pieces.index'), icon: 'fa-boxes', active: true },
];
</script>

<template>
    <Head title="Pièces — Équipement de bureau" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight" :class="t.accent">
                    Équipement de bureau
                </h2>
                <ModuleNavTiles :links="navLinks" />
            </div>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <PieceManager
                    theme="blue"
                    :pieces="pieces"
                    :fournisseurs="fournisseurs"
                    store-url="/equipements-bureau/pieces"
                    update-url-base="/equipements-bureau/pieces"
                    destroy-url-base="/equipements-bureau/pieces"
                    :show-form-block="true"
                    :show-table="true"
                />
            </div>
        </div>
    </AuthenticatedLayout>
</template>
