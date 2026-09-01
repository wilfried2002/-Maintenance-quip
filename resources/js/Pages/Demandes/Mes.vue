<script setup>
import { computed, ref } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DataTable from '@/Components/DataTable.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import { themes } from '@/moduleTheme';

const props = defineProps({
    demandes: { type: Array, required: true },
    equipements: { type: Object, required: true },
    modules: { type: Object, required: true },
});

const t = themes.slate;

const form = useForm({
    module: 'equipements_industriels',
    equipementable_id: '',
    titre: '',
    description: '',
    priorite: 'normale',
});

const equipementsDuModule = computed(() => props.equipements[form.module] ?? []);

function submit() {
    form.post('/demandes', {
        preserveScroll: true,
        onSuccess: () => form.reset('titre', 'description'),
    });
}

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
    { key: 'created_at', label: 'Date' },
    { key: 'titre', label: 'Demande' },
    { key: 'module_label', label: 'Module' },
    { key: 'equipement', label: 'Équipement' },
    { key: 'priorite', label: 'Priorité' },
    { key: 'statut', label: 'Statut' },
];

function dateFr(value) {
    return value ? new Date(value).toLocaleString('fr-FR', { dateStyle: 'short', timeStyle: 'short' }) : '—';
}
</script>

<template>
    <Head title="Mes demandes d'intervention" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight" :class="t.accent">Mes demandes d'intervention</h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <!-- Nouvelle demande -->
                <div class="materio-item card shadow mb-4">
                    <div class="materio-item card-header py-3">
                        <h6 class="materio-item m-0 fw-bold text-primary">Signaler un problème / demander une intervention</h6>
                    </div>
                    <div class="materio-item card-body">
                        <form @submit.prevent="submit">
                            <div class="materio-item row">
                                <div class="materio-item form-group col-md-4">
                                    <InputLabel for="module" value="Module concerné" />
                                    <select id="module" v-model="form.module" class="materio-item form-select" @change="form.equipementable_id = ''">
                                        <option v-for="(label, key) in modules" :key="key" :value="key">{{ label }}</option>
                                    </select>
                                    <InputError :message="form.errors.module" />
                                </div>
                                <div class="materio-item form-group col-md-4">
                                    <InputLabel for="equipementable_id" value="Équipement (facultatif)" />
                                    <select id="equipementable_id" v-model="form.equipementable_id" class="materio-item form-select" :class="{ 'is-invalid': form.errors.equipementable_id }">
                                        <option value="">Je ne sais pas / non listé</option>
                                        <option v-for="equipement in equipementsDuModule" :key="equipement.id" :value="equipement.id">{{ equipement.label }}</option>
                                    </select>
                                    <InputError :message="form.errors.equipementable_id" />
                                </div>
                                <div class="materio-item form-group col-md-4">
                                    <InputLabel for="priorite" value="Urgence ressentie" />
                                    <select id="priorite" v-model="form.priorite" class="materio-item form-select">
                                        <option value="basse">Basse</option>
                                        <option value="normale">Normale</option>
                                        <option value="haute">Haute</option>
                                        <option value="critique">Critique</option>
                                    </select>
                                </div>
                                <div class="materio-item form-group col-md-12">
                                    <InputLabel for="titre" value="Titre" />
                                    <input id="titre" v-model="form.titre" type="text" required class="materio-item form-control" :class="{ 'is-invalid': form.errors.titre }" placeholder="Ex. : climatisation HS au 2ᵉ étage" />
                                    <InputError :message="form.errors.titre" />
                                </div>
                                <div class="materio-item form-group col-md-12">
                                    <InputLabel for="description" value="Description" />
                                    <textarea id="description" v-model="form.description" rows="3" class="materio-item form-control" :class="{ 'is-invalid': form.errors.description }" placeholder="Décrivez le problème constaté…" />
                                    <InputError :message="form.errors.description" />
                                </div>
                            </div>
                            <div class="flex justify-end">
                                <button type="submit" :disabled="form.processing" class="rounded-md px-4 py-2 text-sm font-medium shadow-sm disabled:opacity-50" :class="t.button">
                                    Envoyer la demande
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <DataTable
                    theme="slate"
                    :columns="columns"
                    :rows="props.demandes"
                    search-placeholder="Rechercher dans mes demandes…"
                    empty-text="Aucune demande pour le moment."
                >
                    <template #cell-created_at="{ row }">
                        {{ dateFr(row.created_at) }}
                    </template>
                    <template #cell-titre="{ row }">
                        <div class="font-medium text-gray-800 dark:text-gray-200">{{ row.titre }}</div>
                        <div v-if="row.motif_decision" class="text-xs text-gray-500">Motif : {{ row.motif_decision }}</div>
                        <div v-if="row.intervention" class="text-xs text-green-700">
                            Intervention planifiée le {{ dateFr(row.intervention.date_planifiee) }}
                        </div>
                    </template>
                    <template #cell-statut="{ row }">
                        <span class="inline-flex rounded-full px-2 py-1 text-xs font-medium" :class="statutClasses[row.statut] ?? ''">
                            {{ statutLabels[row.statut] ?? row.statut }}
                        </span>
                    </template>
                </DataTable>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
