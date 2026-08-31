<script setup>
import { ref, watch } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DataTable from '@/Components/DataTable.vue';
import { themes } from '@/moduleTheme';

const props = defineProps({
    activites: { type: Object, required: true },
    actions: { type: Array, required: true },
});

const t = themes.slate;

const columns = [
    { key: 'created_at', label: 'Date' },
    { key: 'user', label: 'Utilisateur' },
    { key: 'action', label: 'Action' },
    { key: 'sujet', label: 'Objet' },
    { key: 'sujet_id', label: 'Réf.' },
    { key: 'changements', label: 'Détails', sortable: false },
];

const actionLabels = {
    creation: 'Création',
    modification: 'Modification',
    suppression: 'Suppression',
    restauration: 'Restauration',
};

const actionClasses = {
    creation: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
    modification: 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
    suppression: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
    restauration: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
};

// Filtre par action : rechargement partiel, fusionné aux requêtes du DataTable
// via la prop filters.
const actionFiltre = ref(new URLSearchParams(window.location.search).get('action') ?? '');

watch(actionFiltre, (valeur) => {
    router.get(window.location.pathname, valeur ? { action: valeur } : {}, {
        only: ['activites'],
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
});

function dateFr(value) {
    return value ? new Date(value).toLocaleString('fr-FR', { dateStyle: 'short', timeStyle: 'short' }) : '—';
}

function nombreChangements(changements) {
    return changements ? Object.keys(changements).length : 0;
}
</script>

<template>
    <Head title="Journal d'activité" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight" :class="t.accent">Journal d'activité</h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="mb-4 flex flex-wrap items-center gap-3">
                    <select v-model="actionFiltre" class="rounded-md border-gray-300 text-sm dark:border-gray-700 dark:bg-gray-900">
                        <option value="">Toutes les actions</option>
                        <option v-for="action in actions" :key="action" :value="action">{{ actionLabels[action] ?? action }}</option>
                    </select>
                </div>

                <DataTable
                    theme="slate"
                    :columns="columns"
                    :rows="props.activites.data"
                    :paginated="props.activites"
                    rows-key="activites"
                    expandable
                    search-placeholder="Rechercher (objet, utilisateur)…"
                    empty-text="Aucune activité enregistrée."
                >
                    <template #cell-created_at="{ row }">
                        {{ dateFr(row.created_at) }}
                    </template>
                    <template #cell-user="{ row }">
                        <span class="font-medium text-gray-800 dark:text-gray-200">{{ row.user }}</span>
                    </template>
                    <template #cell-action="{ row }">
                        <span class="inline-flex rounded-full px-2 py-1 text-xs font-medium" :class="actionClasses[row.action] ?? ''">
                            {{ actionLabels[row.action] ?? row.action }}
                        </span>
                    </template>
                    <template #cell-sujet_id="{ row }">
                        <span class="text-xs text-gray-500">#{{ row.sujet_id }}</span>
                    </template>
                    <template #cell-changements="{ row }">
                        {{ nombreChangements(row.changements) ? nombreChangements(row.changements) + ' champ(s) modifié(s)' : '—' }}
                    </template>

                    <template #expanded="{ row }">
                        <div v-if="row.changements" class="space-y-1 p-2 text-xs">
                            <div v-for="(change, attribut) in row.changements" :key="attribut" class="flex flex-wrap items-center gap-2">
                                <span class="font-semibold">{{ attribut }}</span>
                                <span class="text-red-600 line-through">{{ change.avant ?? '—' }}</span>
                                <span>→</span>
                                <span class="text-green-700">{{ change.apres ?? '—' }}</span>
                            </div>
                        </div>
                        <span v-else class="text-xs text-gray-400">Aucun détail</span>
                    </template>
                </DataTable>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
