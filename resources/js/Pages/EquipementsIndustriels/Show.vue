<script setup>
import { Head } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import EquipmentShow from '@/Components/Modules/EquipmentShow.vue';
import ModuleNavTiles from '@/Components/ModuleNavTiles.vue';
import { themes } from '@/moduleTheme';

const props = defineProps({
    equipement: { type: Object, required: true },
    stats: { type: Object, default: null },
});

const t = themes.orange;

const navLinks = [
    { label: 'Tableau de bord', href: route('equipements-industriels.dashboard'), icon: 'fa-tachometer-alt', active: false },
    { label: 'Équipements', href: route('equipements-industriels.index'), icon: 'fa-industry', active: true },
    { label: 'Interventions', href: route('equipements-industriels.interventions.index'), icon: 'fa-tools', active: false },
    { label: 'Plans préventifs', href: route('equipements-industriels.plans.index'), icon: 'fa-calendar-check', active: false },
    { label: 'Pièces', href: route('equipements-industriels.pieces.index'), icon: 'fa-boxes', active: false },
];

const fields = [
    { key: 'code', label: 'Code' },
    { key: 'categorie', label: 'Catégorie' },
    { key: 'marque', label: 'Marque' },
    { key: 'modele', label: 'Modèle' },
    { key: 'numero_serie', label: 'N° série' },
    { key: 'ligne_production', label: 'Ligne de production' },
    { key: 'puissance_kw', label: 'Puissance (kW)' },
    { key: 'localisation', label: 'Localisation' },
    { key: 'date_mise_service', label: 'Mise en service', type: 'date' },
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
    <Head :title="equipement.designation" />

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
                <EquipmentShow
                    theme="orange"
                    :item="equipement"
                    :fields="fields"
                    :title="equipement.designation"
                    :back-url="route('equipements-industriels.index')"
                    :interventions-url="route('equipements-industriels.interventions.index')"
                    :documents-upload-url="route('equipements-industriels.documents.store', equipement.id)"
                    :documents-destroy-url-base="`/equipements-industriels/${equipement.id}/documents`"
                    :stats="stats"
                    :dashboard-url="route('equipements-industriels.dashboard')"
                />
            </div>
        </div>
    </AuthenticatedLayout>
</template>
