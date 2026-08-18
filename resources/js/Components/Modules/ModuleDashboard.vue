<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { themes } from '@/moduleTheme';
import StatTile from '@/Components/Charts/StatTile.vue';
import AreaLineChart from '@/Components/Charts/AreaLineChart.vue';
import DonutChart from '@/Components/Charts/DonutChart.vue';
import BarChartSimple from '@/Components/Charts/BarChartSimple.vue';
import { formatMontant, symboleDevise } from '@/currency';

const props = defineProps({
    theme: { type: String, required: true },
    stats: { type: Object, required: true },
});

const t = themes[props.theme] ?? themes.slate;
const devise = usePage().props.auth.devise;

function monnaie(v) {
    return formatMontant(v, devise);
}

// Couleurs de statut réservées (good/warning/serious/critical) : le statut d'un
// équipement EST un état, donc la palette de statut est plus juste ici qu'une
// palette catégorielle générique.
const statutDonut = computed(() => [
    { label: 'En service', value: props.stats.parStatut.en_service, color: '#0ca30c' },
    { label: 'En maintenance', value: props.stats.parStatut.en_maintenance, color: '#fab219' },
    { label: 'En panne', value: props.stats.parStatut.en_panne, color: '#d03b3b' },
    { label: 'Hors service', value: props.stats.parStatut.hors_service, color: '#ec835a' },
    { label: 'Réformé', value: props.stats.parStatut.reforme, color: '#9ca3af' },
].filter((s) => s.value > 0));

const topEquipementsBars = computed(() =>
    props.stats.topEquipements.map((e) => ({ label: e.label, value: e.total, color: '#2a78d6' }))
);
</script>

<template>
    <div>
        <div class="materio-item row">
            <div class="materio-item col-xl-3 col-md-6 mb-4">
                <StatTile label="Équipements" :value="stats.total" icon="fa-cubes" variant="primary" />
            </div>
            <div class="materio-item col-xl-3 col-md-6 mb-4">
                <StatTile
                    label="Interventions en cours"
                    :value="stats.interventionsEnCours"
                    :delta="stats.interventionsEnRetard > 0 ? `${stats.interventionsEnRetard} en retard` : 'Aucun retard'"
                    :delta-good="stats.interventionsEnRetard === 0"
                    icon="fa-tools"
                    variant="info"
                />
            </div>
            <div class="materio-item col-xl-3 col-md-6 mb-4">
                <StatTile label="Coût total" :value="monnaie(stats.coutTotal)" icon="fa-coins" variant="success" />
            </div>
            <div class="materio-item col-xl-3 col-md-6 mb-4">
                <StatTile
                    label="Plans préventifs actifs"
                    :value="stats.plansActifs"
                    :delta="stats.plansEnRetard > 0 ? `${stats.plansEnRetard} en retard` : 'Tous à jour'"
                    :delta-good="stats.plansEnRetard === 0"
                    icon="fa-calendar-check"
                    :variant="stats.plansEnRetard > 0 ? 'warning' : 'success'"
                />
            </div>
        </div>

        <div class="materio-item row">
            <div class="materio-item col-xl-8 col-lg-7">
                <div class="materio-item card shadow mb-4">
                    <div class="materio-item card-header py-3">
                        <h6 class="materio-item m-0 fw-bold" :class="t.accent">Coûts d'entretien — 6 derniers mois</h6>
                    </div>
                    <div class="materio-item card-body">
                        <p class="materio-item text-xs text-gray-500 mb-3">Main d'œuvre, pièces et prestations cumulées</p>
                        <AreaLineChart
                            :labels="stats.coutsParMois.map((m) => m.label)"
                            :values="stats.coutsParMois.map((m) => m.total)"
                            :unit="` ${symboleDevise(devise)}`"
                        />
                    </div>
                </div>
            </div>

            <div class="materio-item col-xl-4 col-lg-5">
                <div class="materio-item card shadow mb-4">
                    <div class="materio-item card-header py-3">
                        <h6 class="materio-item m-0 fw-bold" :class="t.accent">Répartition par statut</h6>
                    </div>
                    <div class="materio-item card-body">
                        <p class="materio-item text-xs text-gray-500 mb-3">État actuel du parc</p>
                        <DonutChart v-if="statutDonut.length" :segments="statutDonut" center-label="équipements" />
                        <p v-else class="py-8 text-center text-sm text-gray-500">Aucun équipement enregistré.</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="materio-item row">
            <div class="materio-item col-12">
                <div class="materio-item card shadow mb-4">
                    <div class="materio-item card-header py-3">
                        <h6 class="materio-item m-0 fw-bold" :class="t.accent">Équipements les plus coûteux</h6>
                    </div>
                    <div class="materio-item card-body">
                        <p class="materio-item text-xs text-gray-500 mb-3">Main d'œuvre + pièces + prestations, toutes périodes</p>
                        <BarChartSimple v-if="topEquipementsBars.length" :bars="topEquipementsBars" :unit="` ${symboleDevise(devise)}`" />
                        <p v-else class="py-4 text-center text-sm text-gray-500">Aucun coût enregistré pour le moment.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
