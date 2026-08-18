<script setup>
import { Head } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import EquipmentShow from '@/Components/Modules/EquipmentShow.vue';
import ModuleNavTiles from '@/Components/ModuleNavTiles.vue';
import { themes } from '@/moduleTheme';

const props = defineProps({
    vehicule: { type: Object, required: true },
    stats: { type: Object, default: null },
});

const t = themes.green;

const navLinks = [
    { label: 'Tableau de bord', href: route('vehicules.dashboard'), icon: 'fa-tachometer-alt', active: false },
    { label: 'Véhicules', href: route('vehicules.index'), icon: 'fa-truck', active: true },
    { label: 'Interventions', href: route('vehicules.interventions.index'), icon: 'fa-tools', active: false },
    { label: 'Plans préventifs', href: route('vehicules.plans.index'), icon: 'fa-calendar-check', active: false },
    { label: 'Pièces', href: route('vehicules.pieces.index'), icon: 'fa-boxes', active: false },
];

const fields = [
    { key: 'code', label: 'Code' },
    { key: 'immatriculation', label: 'Immatriculation' },
    { key: 'marque', label: 'Marque' },
    { key: 'modele', label: 'Modèle' },
    { key: 'type_vehicule', label: 'Type' },
    { key: 'type_carburant', label: 'Carburant' },
    { key: 'kilometrage_actuel', label: 'Kilométrage' },
    { key: 'date_mise_circulation', label: 'Mise en circulation', type: 'date' },
    { key: 'date_acquisition', label: 'Acquisition', type: 'date' },
    { key: 'valeur_acquisition', label: "Valeur d'acquisition" },
    { key: 'statut', label: 'Statut' },
    { key: 'criticite', label: 'Criticité' },
    { key: 'date_fin_garantie', label: 'Garantie jusqu\'au', type: 'date' },
    { key: 'fournisseur_id', label: 'Fournisseur' },
    { key: 'notes', label: 'Notes' },
];
</script>

<template>
    <Head :title="vehicule.immatriculation" />

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
                <EquipmentShow
                    theme="green"
                    :item="vehicule"
                    :fields="fields"
                    :title="vehicule.immatriculation"
                    :back-url="route('vehicules.index')"
                    :interventions-url="route('vehicules.interventions.index')"
                    :documents-upload-url="route('vehicules.documents.store', vehicule.id)"
                    :documents-destroy-url-base="`/vehicules/${vehicule.id}/documents`"
                    :stats="stats"
                    :dashboard-url="route('vehicules.dashboard')"
                />
            </div>
        </div>
    </AuthenticatedLayout>
</template>
