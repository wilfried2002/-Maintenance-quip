<script setup>
import { ref, computed } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import { router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { themes } from '@/moduleTheme';

defineProps({
    indicateurs: { type: Array, required: true },
});

const t = themes.slate;
const isRecalculating = ref(false);
const recalculateMessage = ref(null);
const recalculateError = ref(null);

const columns = [
    { key: 'piece.reference', label: 'Référence' },
    { key: 'piece.designation', label: 'Désignation' },
    { key: 'nombre_remplacements', label: 'Remplacements' },
    { key: 'duree_vie_moyenne_jours', label: 'Durée de vie (jours)' },
    { key: 'mtbf_heures', label: 'MTBF (heures)' },
    { key: 'taux_defaillance', label: 'Taux défaillance' },
    { key: 'cout_total_remplacement', label: 'Coût total' },
];

// Filtrer et trier les indicateurs
const indicateursFiltres = computed(() => {
    return indicateurs.filter(i => i.equipementable_type === null && i.equipementable_id === null);
});

const recalculate = async () => {
    isRecalculating.value = true;
    recalculateMessage.value = null;
    recalculateError.value = null;

    try {
        const response = await fetch('/indicateurs/pieces/recalculate', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]').content,
            },
        });

        const data = await response.json();

        if (data.success) {
            recalculateMessage.value = `✅ ${data.message}`;
            // Reload the page after 2 seconds
            setTimeout(() => {
                router.reload();
            }, 2000);
        } else {
            recalculateError.value = `❌ ${data.message}`;
        }
    } catch (error) {
        recalculateError.value = `❌ Erreur : ${error.message}`;
    } finally {
        isRecalculating.value = false;
    }
};

const formatValue = (value, type) => {
    if (value === null || value === undefined) return '—';
    
    switch (type) {
        case 'percentage':
            return `${(value * 100).toFixed(1)}%`;
        case 'currency':
            return `${value.toFixed(2)} €`;
        case 'decimal':
            return value.toFixed(2);
        case 'integer':
            return Math.round(value);
        default:
            return value;
    }
};
</script>

<template>
    <Head title="Indicateurs de Performance — Pièces" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight" :class="t.accent">
                Indicateurs de Performance — Pièces
            </h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <!-- Informations -->
                <div class="materio-item card shadow mb-4">
                    <div class="materio-item card-header py-3">
                        <h6 class="materio-item m-0 fw-bold text-primary">À propos des indicateurs</h6>
                    </div>
                    <div class="materio-item card-body">
                        <p class="text-sm text-gray-700 dark:text-gray-300 mb-3">
                            Les indicateurs de performance des pièces sont calculés automatiquement à partir des données réelles enregistrées 
                            lors des interventions sur les équipements (consommations de pièces), sans intervention manuelle.
                        </p>

                        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
                            <div class="border-l-4 border-blue-500 pl-3">
                                <div class="text-xs font-semibold text-gray-600 dark:text-gray-400">Remplacements</div>
                                <div class="text-sm text-gray-900 dark:text-gray-100">Nombre total de pièces consommées</div>
                            </div>
                            <div class="border-l-4 border-green-500 pl-3">
                                <div class="text-xs font-semibold text-gray-600 dark:text-gray-400">Durée de vie</div>
                                <div class="text-sm text-gray-900 dark:text-gray-100">Écart moyen entre remplacements (jours)</div>
                            </div>
                            <div class="border-l-4 border-purple-500 pl-3">
                                <div class="text-xs font-semibold text-gray-600 dark:text-gray-400">MTBF</div>
                                <div class="text-sm text-gray-900 dark:text-gray-100">Heures de service entre défaillances</div>
                            </div>
                            <div class="border-l-4 border-red-500 pl-3">
                                <div class="text-xs font-semibold text-gray-600 dark:text-gray-400">Taux défaillance</div>
                                <div class="text-sm text-gray-900 dark:text-gray-100">% remplacements lors de pannes</div>
                            </div>
                            <div class="border-l-4 border-orange-500 pl-3">
                                <div class="text-xs font-semibold text-gray-600 dark:text-gray-400">Coût total</div>
                                <div class="text-sm text-gray-900 dark:text-gray-100">Investissement en remplacements</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Bouton de recalcul -->
                <div class="mb-4 flex items-center gap-2">
                    <button
                        @click="recalculate"
                        :disabled="isRecalculating"
                        class="inline-flex items-center rounded-md px-4 py-2 text-sm font-medium shadow-sm disabled:opacity-50"
                        :class="t.button"
                    >
                        <i v-if="!isRecalculating" class="ri ri-refresh-line mr-2"></i>
                        <i v-else class="ri ri-loader-4-line mr-2 animate-spin"></i>
                        {{ isRecalculating ? 'Recalcul en cours...' : 'Recalculer maintenant' }}
                    </button>
                </div>

                <!-- Messages -->
                <div v-if="recalculateMessage" class="mb-4 rounded-lg bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 p-4">
                    <div class="flex items-center gap-2">
                        <i class="ri ri-check-double-line text-green-600 dark:text-green-400"></i>
                        <p class="text-sm text-green-800 dark:text-green-300">{{ recalculateMessage }}</p>
                    </div>
                </div>

                <div v-if="recalculateError" class="mb-4 rounded-lg bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 p-4">
                    <div class="flex items-center gap-2">
                        <i class="ri ri-error-warning-line text-red-600 dark:text-red-400"></i>
                        <p class="text-sm text-red-800 dark:text-red-300">{{ recalculateError }}</p>
                    </div>
                </div>

                <!-- Tableau des indicateurs -->
                <div class="materio-item card shadow">
                    <div class="materio-item card-header py-3">
                        <h6 class="materio-item m-0 fw-bold text-primary">
                            {{ indicateursFiltres.length }} indicateur{{ indicateursFiltres.length > 1 ? 's' : '' }}
                        </h6>
                    </div>

                    <div class="materio-item card-body">
                        <div v-if="indicateursFiltres.length === 0" class="py-8 text-center text-gray-500">
                            <p>Aucun indicateur disponible. Enregistrez des interventions avec consommations de pièces pour voir les indicateurs.</p>
                        </div>

                        <div v-else class="table-responsive">
                            <table class="materio-item table table-bordered table-hover" width="100%">
                                <thead class="materio-item table-light">
                                    <tr>
                                        <th v-for="col in columns" :key="col.key" class="materio-item">
                                            {{ col.label }}
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="indicateur in indicateursFiltres" :key="indicateur.id">
                                        <td class="materio-item">
                                            <span class="font-medium">{{ indicateur.piece?.reference ?? '—' }}</span>
                                        </td>
                                        <td class="materio-item">
                                            {{ indicateur.piece?.designation ?? '—' }}
                                        </td>
                                        <td class="materio-item text-center">
                                            <span class="inline-flex rounded-full bg-blue-100 dark:bg-blue-900 px-2 py-1 text-xs font-medium text-blue-800 dark:text-blue-300">
                                                {{ formatValue(indicateur.nombre_remplacements, 'integer') }}
                                            </span>
                                        </td>
                                        <td class="materio-item text-center">
                                            {{ formatValue(indicateur.duree_vie_moyenne_jours, 'decimal') }}
                                        </td>
                                        <td class="materio-item text-center">
                                            {{ formatValue(indicateur.mtbf_heures, 'decimal') }}
                                        </td>
                                        <td class="materio-item text-center">
                                            <span 
                                                class="inline-flex rounded-full px-2 py-1 text-xs font-medium"
                                                :class="indicateur.taux_defaillance > 0.5 
                                                    ? 'bg-red-100 dark:bg-red-900 text-red-800 dark:text-red-300' 
                                                    : 'bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-300'"
                                            >
                                                {{ formatValue(indicateur.taux_defaillance, 'percentage') }}
                                            </span>
                                        </td>
                                        <td class="materio-item text-right">
                                            <span class="font-medium text-orange-600 dark:text-orange-400">
                                                {{ formatValue(indicateur.cout_total_remplacement, 'currency') }}
                                            </span>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="materio-item card-footer py-3 text-xs text-gray-500 dark:text-gray-400">
                        <i class="ri ri-information-line"></i>
                        Dernière mise à jour : {{ new Date().toLocaleDateString('fr-FR') }}
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
.animate-spin {
    animation: spin 1s linear infinite;
}

@keyframes spin {
    from {
        transform: rotate(0deg);
    }
    to {
        transform: rotate(360deg);
    }
}
</style>
