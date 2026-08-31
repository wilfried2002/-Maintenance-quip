<script setup>
import { computed } from 'vue';
import { useForm, Head, usePage, Link } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DataTable from '@/Components/DataTable.vue';
import { themes } from '@/moduleTheme';

const props = defineProps({
    indicateurs: { type: Array, required: true },
});

const t = themes.purple;

const columns = [
    { key: 'reference', label: 'Référence' },
    { key: 'designation', label: 'Désignation' },
    { key: 'nombre_remplacements', label: 'Remplacements' },
    { key: 'duree_vie_moyenne_jours', label: 'Durée de vie moyenne' },
    { key: 'taux_defaillance', label: 'Taux de défaillance' },
    { key: 'cout_total_remplacement', label: 'Coût total' },
    { key: 'derniere_maj', label: 'Dernière mise à jour' },
];

// Aplati pour DataTable : évite tout nom de slot avec un point (v-slot ne supporte pas
// les modificateurs, mais autant rester sans ambiguïté).
const rows = computed(() => props.indicateurs.map((ind) => ({
    ...ind,
    reference: ind.piece?.reference,
    designation: ind.piece?.designation,
})));

const form = useForm({});

function recalculer() {
    form.post(route('indicateurs.recalculer'), {
        preserveScroll: true,
        preserveState: false,
    });
}

function pourcentage(valeur) {
    return valeur === null || valeur === undefined ? '—' : `${(valeur * 100).toFixed(0)} %`;
}

function jours(valeur) {
    return valeur === null || valeur === undefined ? '—' : `${Math.round(valeur)} j`;
}

function dateFr(valeur) {
    return valeur ? new Date(valeur).toLocaleDateString('fr-FR') : '—';
}

function tauxClasses(valeur) {
    if (valeur === null || valeur === undefined) {
        return 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300';
    }
    if (valeur >= 0.5) {
        return 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200';
    }
    if (valeur >= 0.2) {
        return 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200';
    }
    return 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200';
}
</script>

<template>
    <Head title="Indicateurs de performance des pièces" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight" :class="t.accent">
                    Indicateurs de performance des pièces
                </h2>
                <button
                    type="button"
                    @click="recalculer"
                    :disabled="form.processing"
                    class="inline-flex items-center rounded-md px-4 py-2 text-sm font-medium shadow-sm disabled:opacity-50"
                    :class="t.button"
                >
                    <span v-if="form.processing">Calcul en cours…</span>
                    <span v-else>Recalculer maintenant</span>
                </button>
            </div>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <p class="mb-4 text-sm text-gray-600 dark:text-gray-400">
                    Calculés à partir des consommations réelles enregistrées sur les interventions (aucune saisie manuelle).
                    Le taux de défaillance est la part des remplacements survenus lors d'une intervention corrective plutôt que préventive.
                </p>

                <DataTable
                    theme="purple"
                    :columns="columns"
                    :rows="rows"
                    search-placeholder="Rechercher une pièce…"
                    empty-text="Aucun indicateur pour le moment — cliquez sur « Recalculer maintenant » une fois des pièces consommées sur des interventions."
                >
                    <template #cell-designation="{ row }">
                        <span class="font-medium text-gray-800 dark:text-gray-200">{{ row.designation }}</span>
                    </template>
                    <template #cell-reference="{ row }">
                        <Link :href="route('indicateurs.show', row.piece_id)" class="font-medium text-purple-700 hover:text-purple-900 dark:text-purple-300">
                            {{ row.reference }}
                        </Link>
                    </template>
                    <template #cell-duree_vie_moyenne_jours="{ row }">
                        {{ jours(row.duree_vie_moyenne_jours) }}
                    </template>
                    <template #cell-taux_defaillance="{ row }">
                        <span class="inline-flex rounded-full px-2 py-1 text-xs font-medium" :class="tauxClasses(row.taux_defaillance)">
                            {{ pourcentage(row.taux_defaillance) }}
                        </span>
                    </template>
                    <template #cell-derniere_maj="{ row }">
                        {{ dateFr(row.derniere_maj) }}
                    </template>
                </DataTable>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
