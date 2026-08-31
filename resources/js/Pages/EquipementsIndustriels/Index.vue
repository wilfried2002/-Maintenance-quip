<script setup>
import { computed } from 'vue';
import { Head, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import EquipmentManager from '@/Components/Modules/EquipmentManager.vue';
import StatTile from '@/Components/Charts/StatTile.vue';
import AreaLineChart from '@/Components/Charts/AreaLineChart.vue';
import ModuleNavTiles from '@/Components/ModuleNavTiles.vue';
import { themes, statutOptions, criticiteOptions } from '@/moduleTheme';
import { formatMontant, symboleDevise } from '@/currency';

const props = defineProps({
    equipements: { type: [Array, Object], required: true },
    fournisseurs: { type: Array, required: true },
    stats: { type: Object, required: true },
});

const t = themes.orange;
const devise = usePage().props.auth.devise;

const navLinks = [
    { label: 'Tableau de bord', href: route('equipements-industriels.dashboard'), icon: 'fa-tachometer-alt', active: false },
    { label: 'Équipements', href: route('equipements-industriels.index'), icon: 'fa-industry', active: true },
    { label: 'Interventions', href: route('equipements-industriels.interventions.index'), icon: 'fa-tools', active: false },
    { label: 'Plans préventifs', href: route('equipements-industriels.plans.index'), icon: 'fa-calendar-check', active: false },
    { label: 'Pièces', href: route('equipements-industriels.pieces.index'), icon: 'fa-boxes', active: false },
];

const fields = computed(() => [
    { key: 'code', label: 'Code', required: true },
    { key: 'designation', label: 'Désignation', required: true, wide: true },
    { key: 'categorie', label: 'Catégorie' },
    { key: 'marque', label: 'Marque' },
    { key: 'modele', label: 'Modèle' },
    { key: 'numero_serie', label: 'N° série' },
    { key: 'ligne_production', label: 'Ligne de production' },
    { key: 'puissance_kw', label: 'Puissance (kW)', type: 'number' },
    { key: 'localisation', label: 'Localisation' },
    { key: 'date_mise_service', label: 'Mise en service', type: 'date', column: false },
    { key: 'date_acquisition', label: 'Acquisition', type: 'date', column: false },
    { key: 'valeur_acquisition', label: "Valeur d'acquisition", type: 'number', column: false },
    { key: 'statut', label: 'Statut', type: 'select', required: true, options: statutOptions },
    { key: 'criticite', label: 'Criticité', type: 'select', required: true, default: 'moyenne', options: criticiteOptions, column: false },
    { key: 'date_fin_garantie', label: 'Fin de garantie', type: 'date', column: false },
    {
        key: 'fournisseur_id',
        label: 'Fournisseur',
        type: 'select',
        column: false,
        options: props.fournisseurs.map((f) => ({ value: f.id, label: f.nom })),
    },
    { key: 'notes', label: 'Notes', type: 'textarea', wide: true, column: false },
]);
</script>

<template>
    <Head title="Équipements industriels" />

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
                <!-- Récap du module -->
                <div class="materio-item row">
                    <div class="materio-item col-xl-3 col-md-6 mb-4">
                        <StatTile label="Équipements" :value="stats.total" icon="fa-industry" variant="primary" />
                    </div>
                    <div class="materio-item col-xl-3 col-md-6 mb-4">
                        <StatTile
                            label="Interventions en cours"
                            :value="stats.interventionsEnCours"
                            :delta="stats.interventionsEnRetard > 0 ? `${stats.interventionsEnRetard} en retard` : 'Aucun retard'"
                            :delta-good="stats.interventionsEnRetard === 0"
                            icon="fa-tools"
                            variant="info"
                            :href="route('equipements-industriels.interventions.index')"
                        />
                    </div>
                    <div class="materio-item col-xl-3 col-md-6 mb-4">
                        <StatTile label="Coût total" :value="formatMontant(stats.coutTotal, devise)" icon="fa-coins" variant="success" :href="route('equipements-industriels.dashboard')" />
                    </div>
                    <div class="materio-item col-xl-3 col-md-6 mb-4">
                        <StatTile
                            label="Plans préventifs actifs"
                            :value="stats.plansActifs"
                            :delta="stats.plansEnRetard > 0 ? `${stats.plansEnRetard} en retard` : 'Tous à jour'"
                            :delta-good="stats.plansEnRetard === 0"
                            icon="fa-calendar-check"
                            :variant="stats.plansEnRetard > 0 ? 'warning' : 'success'"
                            :href="route('equipements-industriels.plans.index')"
                        />
                    </div>
                </div>

                <EquipmentManager
                    theme="orange"
                    :items="equipements"
                    :fields="fields"
                    store-url="/equipements-industriels"
                    update-url-base="/equipements-industriels"
                    destroy-url-base="/equipements-industriels"
                    show-url-base="/equipements-industriels"
                    :item-label="(item) => item.designation"
                    :show-form-block="true"
                    :show-table="true"
                />

                <div class="materio-item card shadow mb-4">
                    <div class="materio-item card-header py-3">
                        <h6 class="materio-item m-0 fw-bold text-primary">Coûts d'entretien — 6 derniers mois</h6>
                    </div>
                    <div class="materio-item card-body">
                        <AreaLineChart
                            :labels="stats.coutsParMois.map((m) => m.label)"
                            :values="stats.coutsParMois.map((m) => m.total)"
                            :unit="` ${symboleDevise(devise)}`"
                        />
                    </div>
                </div>
                <!-- table already shown above the chart by the manager -->
            </div>
        </div>
    </AuthenticatedLayout>
</template>
