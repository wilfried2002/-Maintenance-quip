<script setup>
import { computed, ref } from 'vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import DataTable from '@/Components/DataTable.vue';
import { themes } from '@/moduleTheme';
import { formatMontant } from '@/currency';

const equipementColumns = [
    { key: 'label', label: 'Équipement' },
    { key: 'total', label: 'Coût total' },
];

const journalColumns = [
    { key: 'date', label: 'Date' },
    { key: 'equipementable', label: 'Équipement', sortable: false },
    { key: 'type_cout', label: 'Type' },
    { key: 'description', label: 'Description' },
    { key: 'montant', label: 'Montant' },
];

const props = defineProps({
    couts: { type: [Array, Object], required: true },
    totalParType: { type: Object, required: true },
    totalGeneral: { type: Number, required: true },
    parEquipement: { type: Array, required: true },
    equipements: { type: Object, required: true },
});

const t = themes.teal;

// Journal paginé côté serveur (paginator Laravel) — compat tableau conservée.
const coutsRows = computed(() =>
    Array.isArray(props.couts) ? props.couts : (props.couts?.data ?? []));
const coutsPaginees = computed(() =>
    Array.isArray(props.couts) ? null : props.couts);

const typeCoutLabels = {
    main_oeuvre: "Main d'œuvre",
    pieces: 'Pièces',
    prestation_externe: 'Prestation externe',
    autre: 'Autre',
};

const showForm = ref(false);

const form = useForm({
    type_equipement: 'industriel',
    equipementable_id: '',
    type_cout: 'prestation_externe',
    montant: '',
    date: new Date().toISOString().slice(0, 10),
    description: '',
});

const equipementsDisponibles = computed(() => props.equipements[form.type_equipement] ?? []);

function equipementLabel(eq) {
    return `${eq.code} — ${eq.designation ?? eq.immatriculation ?? ''}`;
}

function submit() {
    form.post('/couts', {
        preserveScroll: true,
        onSuccess: () => {
            form.reset('equipementable_id', 'montant', 'description');
            showForm.value = false;
        },
    });
}

const deleteForm = useForm({});

function destroy(cout) {
    if (cout.intervention_id) {
        alert("Ce coût provient d'une intervention : modifiez l'intervention plutôt que ce coût.");
        return;
    }
    if (confirm('Supprimer ce coût ?')) {
        deleteForm.delete(`/couts/${cout.id}`, { preserveScroll: true });
    }
}

function equipementableLabel(cout) {
    const eq = cout.equipementable;
    if (!eq) return '—';
    return `${eq.code} — ${eq.designation ?? eq.immatriculation ?? ''}`;
}

const devise = usePage().props.auth.devise;

function monnaie(valeur) {
    return formatMontant(valeur, devise, { decimales: 2 });
}

function dateFr(valeur) {
    return valeur ? new Date(valeur).toLocaleDateString('fr-FR') : '—';
}
</script>

<template>
    <Head title="Coûts d'entretien" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight" :class="t.accent">Coûts d'entretien</h2>
                <a
                    href="/couts/export"
                    class="inline-flex items-center rounded-md px-4 py-2 text-sm font-medium shadow-sm"
                    :class="t.button"
                >
                    Exporter en CSV
                </a>
            </div>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <!-- Résumé -->
                <div class="mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
                    <div class="rounded-lg border p-4" :class="t.header">
                        <p class="text-xs font-semibold uppercase tracking-wide" :class="t.accent">Total général</p>
                        <p class="mt-1 text-2xl font-semibold text-gray-800 dark:text-gray-100">{{ monnaie(totalGeneral) }}</p>
                    </div>
                    <div v-for="(montant, type) in totalParType" :key="type" class="rounded-lg border p-4" :class="t.header">
                        <p class="text-xs font-semibold uppercase tracking-wide" :class="t.accent">{{ typeCoutLabels[type] }}</p>
                        <p class="mt-1 text-2xl font-semibold text-gray-800 dark:text-gray-100">{{ monnaie(montant) }}</p>
                    </div>
                </div>

                <!-- Par équipement -->
                <div class="mb-6">
                    <DataTable
                        theme="teal"
                        :columns="equipementColumns"
                        :rows="parEquipement"
                        row-key="label"
                        search-placeholder="Rechercher un équipement…"
                        empty-text="Aucun coût enregistré."
                    >
                        <template #cell-total="{ row }">
                            <span class="font-medium text-gray-800 dark:text-gray-200">{{ monnaie(row.total) }}</span>
                        </template>
                    </DataTable>
                </div>

                <!-- Ajout manuel -->
                <div class="mb-4">
                    <button
                        type="button"
                        @click="showForm = !showForm"
                        class="inline-flex items-center rounded-md px-4 py-2 text-sm font-medium shadow-sm"
                        :class="t.button"
                    >
                        {{ showForm ? 'Annuler' : 'Ajouter un coût (prestation externe / autre)' }}
                    </button>
                </div>

                <div v-if="showForm" class="mb-6 rounded-lg border p-6" :class="t.header">
                    <form @submit.prevent="submit" class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <InputLabel value="Type d'équipement" />
                            <select v-model="form.type_equipement" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100" :class="t.ring">
                                <option value="industriel">Équipement industriel</option>
                                <option value="vehicule">Véhicule</option>
                                <option value="bureau">Équipement de bureau</option>
                            </select>
                        </div>
                        <div>
                            <InputLabel value="Équipement" />
                            <select v-model="form.equipementable_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100" :class="t.ring" required>
                                <option value="" disabled>Sélectionner…</option>
                                <option v-for="eq in equipementsDisponibles" :key="eq.id" :value="eq.id">{{ equipementLabel(eq) }}</option>
                            </select>
                            <InputError class="mt-1" :message="form.errors.equipementable_id" />
                        </div>
                        <div>
                            <InputLabel value="Type de coût" />
                            <select v-model="form.type_cout" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100" :class="t.ring">
                                <option value="prestation_externe">Prestation externe</option>
                                <option value="autre">Autre</option>
                            </select>
                        </div>
                        <div>
                            <InputLabel value="Montant" />
                            <input v-model="form.montant" type="number" step="any" min="0.01" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100" :class="t.ring" required />
                            <InputError class="mt-1" :message="form.errors.montant" />
                        </div>
                        <div>
                            <InputLabel value="Date" />
                            <input v-model="form.date" type="date" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100" :class="t.ring" required />
                        </div>
                        <div>
                            <InputLabel value="Description" />
                            <input v-model="form.description" type="text" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100" :class="t.ring" />
                        </div>
                        <div class="sm:col-span-2 flex justify-end">
                            <button type="submit" :disabled="form.processing" class="rounded-md px-4 py-2 text-sm font-medium shadow-sm disabled:opacity-50" :class="t.button">
                                Enregistrer
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Journal détaillé -->
                <DataTable
                    theme="teal"
                    :columns="journalColumns"
                    :rows="coutsRows"
                    :paginated="coutsPaginees"
                    rows-key="couts"
                    search-placeholder="Rechercher dans le journal…"
                    empty-text="Aucun coût enregistré (hors pièces, comptabilisées ci-dessus)."
                >
                    <template #cell-date="{ row }">
                        {{ dateFr(row.date) }}
                    </template>
                    <template #cell-equipementable="{ row }">
                        {{ equipementableLabel(row) }}
                    </template>
                    <template #cell-type_cout="{ row }">
                        <span class="inline-flex rounded-full px-2 py-1 text-xs font-medium" :class="t.badge">
                            {{ typeCoutLabels[row.type_cout] }}
                        </span>
                    </template>
                    <template #cell-montant="{ row }">
                        <span class="font-medium text-gray-800 dark:text-gray-200">{{ monnaie(row.montant) }}</span>
                    </template>

                    <template #actions="{ row }">
                        <span v-if="row.intervention_id" class="text-xs text-gray-400">Depuis intervention</span>
                        <button v-else type="button" class="font-medium text-red-600 hover:text-red-800" @click="destroy(row)">Supprimer</button>
                    </template>
                </DataTable>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
