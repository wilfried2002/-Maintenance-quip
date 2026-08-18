<script setup>
import { Head, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import StatTile from '@/Components/Charts/StatTile.vue';
import AreaLineChart from '@/Components/Charts/AreaLineChart.vue';
import DonutChart from '@/Components/Charts/DonutChart.vue';
import BarChartSimple from '@/Components/Charts/BarChartSimple.vue';
import Meter from '@/Components/Charts/Meter.vue';
import { formatMontant, symboleDevise } from '@/currency';

const props = defineProps({
    kpis: { type: Object, required: true },
    charts: { type: Object, required: true },
});

const devise = usePage().props.auth.devise;

function monnaie(v) {
    return formatMontant(v, devise);
}

const interventionsDonut = [
    { label: 'Planifiées', value: props.charts.interventionsParStatut.planifiee, color: '#2a78d6' },
    { label: 'En cours', value: props.charts.interventionsParStatut.en_cours, color: '#eb6834' },
    { label: 'Terminées', value: props.charts.interventionsParStatut.terminee, color: '#1baf7a' },
    { label: 'Annulées', value: props.charts.interventionsParStatut.annulee, color: '#9ca3af' },
];

const coutsBars = [
    { label: "Main d'œuvre", value: props.charts.coutsParType.main_oeuvre, color: '#2a78d6' },
    { label: 'Pièces', value: props.charts.coutsParType.pieces, color: '#eb6834' },
    { label: 'Prestation externe', value: props.charts.coutsParType.prestation_externe, color: '#1baf7a' },
    { label: 'Autre', value: props.charts.coutsParType.autre, color: '#eda100' },
];

const plansAJourPercent = props.kpis.plansActifs > 0
    ? ((props.kpis.plansActifs - props.kpis.plansEnRetard) / props.kpis.plansActifs) * 100
    : 100;
</script>

<template>
    <Head title="Tableau de bord" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-lg font-semibold leading-tight text-gray-800 dark:text-gray-100">
                Bonjour {{ $page.props.auth.user.name }}
            </h2>
        </template>

        <div>
            <!-- AuthenticatedLayout enveloppe déjà le <slot/> dans .container-fluid : pas besoin
                 de le redéclarer ici (ça doublerait le padding horizontal). -->
            <!-- KPI -->
            <div class="materio-item row">
                <div class="materio-item col-xl-3 col-md-6 mb-4">
                    <StatTile label="Équipements (tous parcs)" :value="kpis.totalEquipements" icon="fa-industry" variant="primary" />
                </div>
                <div class="materio-item col-xl-3 col-md-6 mb-4">
                    <StatTile label="Interventions en cours" :value="kpis.interventionsEnCours" icon="fa-tools" variant="info" />
                </div>
                <div class="materio-item col-xl-3 col-md-6 mb-4">
                    <StatTile
                        label="Interventions planifiées"
                        :value="kpis.interventionsPlanifiees"
                        :delta="kpis.interventionsEnRetard > 0 ? `${kpis.interventionsEnRetard} en retard` : 'Aucun retard'"
                        :delta-good="kpis.interventionsEnRetard === 0"
                        icon="fa-calendar"
                        variant="warning"
                    />
                </div>
                <div class="materio-item col-xl-3 col-md-6 mb-4">
                    <StatTile label="Coût du mois" :value="monnaie(kpis.coutMois)" icon="fa-coins" variant="success" />
                </div>
            </div>
            <div class="materio-item row">
                <div class="materio-item col-xl-3 col-md-6 mb-4">
                    <StatTile
                        label="Pièces en stock bas"
                        :value="kpis.piecesStockBas"
                        :delta="kpis.piecesStockBas > 0 ? 'À réapprovisionner' : 'Stock au vert'"
                        :delta-good="kpis.piecesStockBas === 0"
                        icon="fa-boxes"
                        :variant="kpis.piecesStockBas > 0 ? 'danger' : 'success'"
                    />
                </div>
            </div>

            <!-- Graphiques -->
            <div class="materio-item row">
                <div class="materio-item col-xl-8 col-lg-7">
                    <div class="materio-item card shadow mb-4">
                        <div class="materio-item card-header py-3">
                            <h6 class="materio-item m-0 fw-bold text-primary">Coûts d'entretien — 6 derniers mois</h6>
                        </div>
                        <div class="materio-item card-body">
                            <p class="materio-item text-xs text-gray-500 mb-3">Main d'œuvre, pièces et prestations cumulées</p>
                            <AreaLineChart
                                :labels="charts.coutsParMois.map((m) => m.label)"
                                :values="charts.coutsParMois.map((m) => m.total)"
                                :unit="` ${symboleDevise(devise)}`"
                            />
                        </div>
                    </div>
                </div>

                <div class="materio-item col-xl-4 col-lg-5">
                    <div class="materio-item card shadow mb-4">
                        <div class="materio-item card-header py-3">
                            <h6 class="materio-item m-0 fw-bold text-primary">Maintenance préventive</h6>
                        </div>
                        <div class="materio-item card-body text-center">
                            <p class="materio-item text-xs text-gray-500 mb-3">Plans actifs à jour vs en retard</p>
                            <div class="d-flex justify-content-center materio-item">
                                <Meter label="plans à jour" :percent="plansAJourPercent" />
                            </div>
                            <p class="materio-item mt-2 small text-gray-500">
                                {{ kpis.plansActifs - kpis.plansEnRetard }} / {{ kpis.plansActifs }} plan(s) actif(s) à jour
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="materio-item row">
                <div class="materio-item col-xl-4 col-lg-5">
                    <div class="materio-item card shadow mb-4">
                        <div class="materio-item card-header py-3">
                            <h6 class="materio-item m-0 fw-bold text-primary">Interventions par statut</h6>
                        </div>
                        <div class="materio-item card-body">
                            <p class="materio-item text-xs text-gray-500 mb-3">Répartition de l'ensemble des interventions</p>
                            <DonutChart :segments="interventionsDonut" center-label="interventions" />
                        </div>
                    </div>
                </div>

                <div class="materio-item col-xl-8 col-lg-7">
                    <div class="materio-item card shadow mb-4">
                        <div class="materio-item card-header py-3">
                            <h6 class="materio-item m-0 fw-bold text-primary">Coûts par type</h6>
                        </div>
                        <div class="materio-item card-body">
                            <p class="materio-item text-xs text-gray-500 mb-3">Toutes périodes confondues</p>
                            <BarChartSimple :bars="coutsBars" :unit="` ${symboleDevise(devise)}`" />
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
