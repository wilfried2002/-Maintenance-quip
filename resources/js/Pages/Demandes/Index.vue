<script setup>
import { ref } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DataTable from '@/Components/DataTable.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import { themes } from '@/moduleTheme';

const props = defineProps({
    demandes: { type: Object, required: true },
});

const t = themes.slate;

const statutLabels = {
    soumise: 'Soumise',
    approuvee: 'Approuvée',
    refusee: 'Refusée',
    convertie: 'Convertie',
};

const statutClasses = {
    soumise: 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
    approuvee: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
    refusee: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
    convertie: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
};

const columns = [
    { key: 'created_at', label: 'Reçue le' },
    { key: 'titre', label: 'Demande' },
    { key: 'demandeur', label: 'Demandeur' },
    { key: 'module_label', label: 'Module' },
    { key: 'priorite', label: 'Priorité' },
    { key: 'statut', label: 'Statut' },
];

const rows = props.demandes.data ?? [];

// Décision (approbation/refus motivé) sur une demande soumise.
const demandeActive = ref(null);
const decisionForm = useForm({ action: 'approuver', motif_decision: '' });

// Conversion d'une demande approuvée en intervention planifiée.
const conversionActive = ref(null);
const conversionForm = useForm({ equipementable_id: '', date_planifiee: '', technicien_id: '' });

function ouvrirDecision(row, action) {
    demandeActive.value = row.id;
    decisionForm.defaults({ action, motif_decision: '' });
    decisionForm.reset();
}

function soumettreDecision(row) {
    decisionForm.post(`/demandes/${row.id}/decision`, {
        preserveScroll: true,
        onSuccess: () => { demandeActive.value = null; },
    });
}

function ouvrirConversion(row) {
    conversionActive.value = row.id;
    conversionForm.defaults({ equipementable_id: '', date_planifiee: '', technicien_id: '' });
    conversionForm.reset();
}

function soumettreConversion(row) {
    conversionForm.post(`/demandes/${row.id}/convertir`, {
        preserveScroll: true,
        onSuccess: () => { conversionActive.value = null; },
    });
}

function dateFr(value) {
    return value ? new Date(value).toLocaleString('fr-FR', { dateStyle: 'short', timeStyle: 'short' }) : '—';
}
</script>

<template>
    <Head title="Demandes d'intervention à traiter" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight" :class="t.accent">Demandes d'intervention</h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <p class="mb-4 text-sm text-gray-600 dark:text-gray-400">
                    Demandes soumises par les utilisateurs finaux sur les modules que vous gérez.
                    Approuvez puis convertissez en intervention planifiée, ou refusez avec motif.
                </p>

                <DataTable
                    theme="slate"
                    :columns="columns"
                    :rows="rows"
                    :paginated="props.demandes"
                    rows-key="demandes"
                    expandable
                    search-placeholder="Rechercher une demande…"
                    empty-text="Aucune demande en attente."
                >
                    <template #cell-created_at="{ row }">
                        {{ dateFr(row.created_at) }}
                    </template>
                    <template #cell-titre="{ row }">
                        <div class="font-medium text-gray-800 dark:text-gray-200">{{ row.titre }}</div>
                        <div class="text-xs text-gray-500">{{ row.equipement ?? 'Équipement non précisé' }}</div>
                    </template>
                    <template #cell-statut="{ row }">
                        <span class="inline-flex rounded-full px-2 py-1 text-xs font-medium" :class="statutClasses[row.statut] ?? ''">
                            {{ statutLabels[row.statut] ?? row.statut }}
                        </span>
                    </template>

                    <template #expanded="{ row }">
                        <div class="space-y-3 p-2">
                            <p class="text-sm"><span class="font-semibold">Description :</span> {{ row.description ?? '—' }}</p>
                            <p v-if="row.motif_decision" class="text-sm">
                                <span class="font-semibold">Décision de {{ row.decideur }} le {{ dateFr(row.decide_le) }} :</span> {{ row.motif_decision }}
                            </p>
                            <p v-if="row.intervention" class="text-sm text-green-700">
                                Intervention #{{ row.intervention.id }} « {{ row.intervention.titre }} » planifiée le {{ dateFr(row.intervention.date_planifiee) }}.
                            </p>

                            <!-- Décision -->
                            <div v-if="row.statut === 'soumise' && demandeActive === row.id" class="rounded-md bg-white p-3 shadow-sm dark:bg-gray-800">
                                <div class="flex flex-col gap-2 sm:flex-row sm:items-end">
                                    <div class="flex-1">
                                        <InputLabel :for="`motif-${row.id}`" value="Motif (requis pour un refus)" />
                                        <input :id="`motif-${row.id}`" v-model="decisionForm.motif_decision" type="text" class="materio-item form-control" :class="{ 'is-invalid': decisionForm.errors.motif_decision }" />
                                        <InputError :message="decisionForm.errors.motif_decision" />
                                    </div>
                                    <button
                                        type="button"
                                        :disabled="decisionForm.processing"
                                        class="rounded-md bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700 disabled:opacity-50"
                                        @click="soumettreDecision(row)"
                                    >
                                        Confirmer
                                    </button>
                                    <button type="button" class="text-sm text-gray-500" @click="demandeActive = null">Annuler</button>
                                </div>
                            </div>

                            <!-- Conversion -->
                            <div v-if="row.statut === 'approuvee' && conversionActive === row.id" class="rounded-md bg-white p-3 shadow-sm dark:bg-gray-800">
                                <div class="flex flex-col gap-2 sm:flex-row sm:items-end">
                                    <div class="flex-1">
                                        <InputLabel :for="`date-${row.id}`" value="Date planifiée" />
                                        <input :id="`date-${row.id}`" v-model="conversionForm.date_planifiee" type="datetime-local" required class="materio-item form-control" :class="{ 'is-invalid': conversionForm.errors.date_planifiee }" />
                                        <InputError :message="conversionForm.errors.date_planifiee" />
                                    </div>
                                    <button
                                        type="button"
                                        :disabled="conversionForm.processing"
                                        class="rounded-md px-4 py-2 text-sm font-medium text-white shadow-sm disabled:opacity-50"
                                        :class="t.button"
                                        @click="soumettreConversion(row)"
                                    >
                                        Planifier l'intervention
                                    </button>
                                    <button type="button" class="text-sm text-gray-500" @click="conversionActive = null">Annuler</button>
                                </div>
                                <p v-if="conversionForm.errors.equipementable_id" class="text-xs text-red-600">{{ conversionForm.errors.equipementable_id }}</p>
                                <p v-if="!row.equipement" class="text-xs text-orange-600">
                                    Cette demande ne précise pas d'équipement : la conversion planifiera sur l'équipement à choisir dans le module concerné (à préciser via la fiche intervention si besoin).
                                </p>
                            </div>
                        </div>
                    </template>

                    <template #actions="{ row }">
                        <template v-if="row.statut === 'soumise'">
                            <button type="button" class="mr-3 font-medium text-green-700 hover:text-green-900" @click="ouvrirDecision(row, 'approuver')">Approuver</button>
                            <button type="button" class="mr-3 font-medium text-red-600 hover:text-red-800" @click="ouvrirDecision(row, 'refuser')">Refuser</button>
                        </template>
                        <button v-if="row.statut === 'approuvee'" type="button" class="font-medium" :class="t.accent" @click="ouvrirConversion(row)">
                            Planifier
                        </button>
                    </template>
                </DataTable>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
