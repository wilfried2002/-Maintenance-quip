<script setup>
import { Head } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PlanMaintenanceManager from '@/Components/Modules/PlanMaintenanceManager.vue';
import ModuleNavTiles from '@/Components/ModuleNavTiles.vue';
import { themes } from '@/moduleTheme';

defineProps({
    plans: { type: Array, required: true },
    equipements: { type: Array, required: true },
});

const t = themes.blue;

const navLinks = [
    { label: 'Tableau de bord', href: route('equipements-bureau.dashboard'), icon: 'fa-tachometer-alt', active: false },
    { label: 'Équipements', href: route('equipements-bureau.index'), icon: 'fa-desktop', active: false },
    { label: 'Interventions', href: route('equipements-bureau.interventions.index'), icon: 'fa-tools', active: false },
    { label: 'Plans préventifs', href: route('equipements-bureau.plans.index'), icon: 'fa-calendar-check', active: true },
    { label: 'Pièces', href: route('equipements-bureau.pieces.index'), icon: 'fa-boxes', active: false },
];
</script>

<template>
    <Head title="Plans de maintenance — Équipement de bureau" />

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
                    <PlanMaintenanceManager
                        theme="blue"
                        :plans="plans"
                        :equipements="equipements"
                        store-url="/equipements-bureau/plans"
                        update-url-base="/equipements-bureau/plans"
                        destroy-url-base="/equipements-bureau/plans"
                        execute-url-base="/equipements-bureau/plans"
                        equipement-label-key="designation"
                        :show-form-block="true"
                        :show-table="true"
                    />
            </div>
        </div>
    </AuthenticatedLayout>
</template>
