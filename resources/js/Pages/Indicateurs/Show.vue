<script setup>
import { computed } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DataTable from '@/Components/DataTable.vue';
import { formatMontant } from '@/currency';

const props = defineProps({
    piece: { type: Object, required: true },
    indicateur: { type: Object, default: null },
    consommations: { type: Array, required: true },
});

const devise = usePage().props.auth.devise;
const columns = [
    { key: 'date', label: 'Date' },
    { key: 'equipement', label: 'Équipement' },
    { key: 'type_intervention', label: 'Type' },
    { key: 'titre', label: 'Intervention' },
    { key: 'quantite', label: 'Quantité', align: 'right' },
    { key: 'cout_total', label: 'Coût', align: 'right' },
];

const totalCorrectif = computed(() => props.consommations
    .filter((consommation) => consommation.type_intervention === 'corrective')
    .reduce((total, consommation) => total + Number(consommation.quantite), 0));

function dateFr(value) {
    return value ? new Date(value).toLocaleDateString('fr-FR') : '—';
}

function monnaie(value) {
    return formatMontant(value, devise, { decimales: 2 });
}

function pourcentage(value) {
    return value === null || value === undefined ? '—' : `${(Number(value) * 100).toFixed(0)} %`;
}

function jours(value) {
    return value === null || value === undefined ? '—' : `${Math.round(Number(value))} j`;
}
</script>

<template>
    <Head :title="`Indicateurs — ${piece.reference}`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between gap-4">
                <div>
                    <Link :href="route('indicateurs.index')" class="text-sm text-purple-700 hover:text-purple-900 dark:text-purple-300">
                        ← Retour aux indicateurs
                    </Link>
                    <h2 class="mt-1 text-xl font-semibold leading-tight text-purple-700 dark:text-purple-300">
                        {{ piece.reference }} — {{ piece.designation }}
                    </h2>
                </div>
            </div>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <div class="rounded-lg border border-gray-200 p-4 dark:border-gray-700">
                        <p class="text-xs font-semibold uppercase text-gray-500">Remplacements</p>
                        <p class="mt-1 text-2xl font-semibold">{{ indicateur?.nombre_remplacements ?? 0 }}</p>
                    </div>
                    <div class="rounded-lg border border-gray-200 p-4 dark:border-gray-700">
                        <p class="text-xs font-semibold uppercase text-gray-500">Taux de défaillance</p>
                        <p class="mt-1 text-2xl font-semibold">{{ pourcentage(indicateur?.taux_defaillance) }}</p>
                        <p class="mt-1 text-xs text-gray-500">{{ totalCorrectif }} en correctif</p>
                    </div>
                    <div class="rounded-lg border border-gray-200 p-4 dark:border-gray-700">
                        <p class="text-xs font-semibold uppercase text-gray-500">Durée de vie moyenne</p>
                        <p class="mt-1 text-2xl font-semibold">{{ jours(indicateur?.duree_vie_moyenne_jours) }}</p>
                    </div>
                    <div class="rounded-lg border border-gray-200 p-4 dark:border-gray-700">
                        <p class="text-xs font-semibold uppercase text-gray-500">Coût total</p>
                        <p class="mt-1 text-2xl font-semibold">{{ monnaie(indicateur?.cout_total_remplacement ?? 0) }}</p>
                    </div>
                </div>

                <p class="mb-4 text-sm text-gray-600 dark:text-gray-400">
                    Historique des consommations utilisées pour calculer ces indicateurs.
                </p>

                <DataTable
                    :columns="columns"
                    :rows="consommations"
                    search-placeholder="Rechercher une intervention…"
                    empty-text="Aucune consommation enregistrée pour cette pièce."
                >
                    <template #cell-date="{ row }">{{ dateFr(row.date) }}</template>
                    <template #cell-type_intervention="{ row }">
                        <span class="inline-flex rounded-full px-2 py-1 text-xs font-medium" :class="row.type_intervention === 'corrective' ? 'bg-red-100 text-red-800' : 'bg-green-100 text-green-800'">
                            {{ row.type_intervention === 'corrective' ? 'Corrective' : 'Préventive' }}
                        </span>
                    </template>
                    <template #cell-cout_total="{ row }">{{ monnaie(row.cout_total) }}</template>
                </DataTable>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
