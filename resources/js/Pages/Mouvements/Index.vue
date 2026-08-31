<script setup>
import { Head } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DataTable from '@/Components/DataTable.vue';
import { themes } from '@/moduleTheme';

defineProps({
    mouvements: { type: Array, required: true },
});

const t = themes.amber;

const columns = [
    { key: 'created_at', label: 'Date' },
    { key: 'piece', label: 'Pièce' },
    { key: 'module_label', label: 'Module' },
    { key: 'type', label: 'Type' },
    { key: 'quantite', label: 'Quantité' },
    { key: 'stock_apres', label: 'Stock après' },
    { key: 'motif', label: 'Motif' },
    { key: 'user', label: 'Par' },
];

const typeLabels = {
    entree: 'Entrée',
    sortie: 'Sortie',
    ajustement: 'Ajustement',
};

const typeClasses = {
    entree: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
    sortie: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
    ajustement: 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300',
};

function dateFr(value) {
    return value ? new Date(value).toLocaleString('fr-FR', { dateStyle: 'short', timeStyle: 'short' }) : '—';
}
</script>

<template>
    <Head title="Mouvements de stock" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight" :class="t.accent">Mouvements de stock</h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <p class="mb-4 text-sm text-gray-600 dark:text-gray-400">
                    Journal des entrées (réapprovisionnements), sorties manuelles et ajustements d'inventaire,
                    limité aux stocks des modules que vous gérez. Les consommations liées aux interventions
                    figurent sur la fiche de l'intervention concernée.
                </p>

                <DataTable
                    theme="amber"
                    :columns="columns"
                    :rows="mouvements"
                    search-placeholder="Rechercher un mouvement…"
                    empty-text="Aucun mouvement enregistré."
                >
                    <template #cell-created_at="{ row }">
                        {{ dateFr(row.created_at) }}
                    </template>
                    <template #cell-piece="{ row }">
                        <span class="font-medium text-gray-800 dark:text-gray-200">{{ row.piece }}</span>
                    </template>
                    <template #cell-type="{ row }">
                        <span
                            class="inline-flex rounded-full px-2 py-1 text-xs font-medium"
                            :class="typeClasses[row.type] ?? typeClasses.ajustement"
                        >
                            {{ typeLabels[row.type] ?? row.type }}
                        </span>
                    </template>
                    <template #cell-quantite="{ row }">
                        <span :class="row.type === 'sortie' ? 'text-red-600 dark:text-red-400' : 'text-green-700 dark:text-green-400'">
                            {{ row.type === 'sortie' ? '−' : '+' }}{{ row.quantite }} {{ row.unite }}
                        </span>
                    </template>
                    <template #cell-stock_apres="{ row }">
                        <span class="font-semibold">{{ row.stock_apres }}</span>
                    </template>
                </DataTable>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
