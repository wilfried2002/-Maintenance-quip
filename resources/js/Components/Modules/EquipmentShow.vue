<script setup>
import { ref } from 'vue';
import { Link, useForm, usePage } from '@inertiajs/vue3';
import { themes, statutOptions, criticiteBadgeClasses, statutInterventionOptions, typeInterventionOptions } from '@/moduleTheme';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import StatTile from '@/Components/Charts/StatTile.vue';
import { symboleDevise } from '@/currency';

const props = defineProps({
    theme: { type: String, required: true },
    item: { type: Object, required: true },
    fields: { type: Array, required: true },
    title: { type: String, required: true },
    backUrl: { type: String, required: true },
    interventionsUrl: { type: String, required: true },
    documentsUploadUrl: { type: String, required: true },
    documentsDestroyUrlBase: { type: String, required: true },
    stats: { type: Object, default: null },
    dashboardUrl: { type: String, default: null },
});

const t = themes[props.theme] ?? themes.slate;

function statutLabel(value) {
    return statutOptions.find((o) => o.value === value)?.label ?? value;
}

function statutInterventionLabel(value) {
    return statutInterventionOptions.find((o) => o.value === value)?.label ?? value;
}

function typeInterventionLabel(value) {
    return typeInterventionOptions.find((o) => o.value === value)?.label ?? value;
}

function formatDate(value) {
    if (!value) return '—';
    return new Date(value).toLocaleDateString('fr-FR');
}

const devise = usePage().props.auth.devise;

function formatMontant(value) {
    if (!value && value !== 0) return '—';
    return `${Number(value).toLocaleString('fr-FR')} ${symboleDevise(devise)}`;
}

const statutInterventionClasses = {
    planifiee: 'bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-200',
    en_cours: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
    terminee: 'bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-200',
    annulee: 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-300',
};

function fieldValue(field) {
    if (field.key === 'fournisseur_id') {
        return props.item.fournisseur?.nom ?? '—';
    }
    const value = props.item[field.key];
    if (!value) return '—';
    if (field.type === 'date') {
        return new Date(value).toLocaleDateString('fr-FR');
    }
    return value;
}

function formatSize(bytes) {
    if (!bytes) return '';
    const kb = bytes / 1024;
    return kb < 1024 ? `${kb.toFixed(0)} Ko` : `${(kb / 1024).toFixed(1)} Mo`;
}

const documentsForm = useForm({ documents: [] });

function onDocumentsChange(event) {
    documentsForm.documents = Array.from(event.target.files);
}

function submitDocuments() {
    documentsForm.post(props.documentsUploadUrl, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            documentsForm.reset();
        },
    });
}

const deleteForm = useForm({});

function destroyDocument(document) {
    if (confirm(`Supprimer « ${document.nom_original} » ?`)) {
        deleteForm.delete(`${props.documentsDestroyUrlBase}/${document.id}`, { preserveScroll: true });
    }
}
</script>

<template>
    <div>
        <div class="mb-4 flex items-center justify-between">
            <Link :href="backUrl" class="text-sm font-medium" :class="t.accent">
                &larr; Retour à la liste
            </Link>
            <div class="flex items-center gap-2">
                <Link
                    v-if="dashboardUrl"
                    :href="dashboardUrl"
                    class="inline-flex items-center rounded-md px-4 py-2 text-sm font-medium shadow-sm"
                    :class="t.button"
                >
                    Tableau de bord du module
                </Link>
                <Link
                    :href="interventionsUrl"
                    class="inline-flex items-center rounded-md px-4 py-2 text-sm font-medium shadow-sm"
                    :class="t.button"
                >
                    Voir les interventions
                </Link>
            </div>
        </div>

        <!-- Tableau de bord de l'équipement -->
        <div v-if="stats" class="mb-6 grid grid-cols-2 gap-4 sm:grid-cols-4">
            <StatTile label="Interventions" :value="stats.nombreInterventions" :accent="t.accent" />
            <StatTile label="Coût total" :value="formatMontant(stats.coutTotal)" :accent="t.accent" />
            <StatTile label="Dernière intervention" :value="formatDate(stats.derniereInterventionDate)" :accent="t.accent" />
            <StatTile
                label="Prochaine échéance"
                :value="formatDate(stats.prochaineEcheance)"
                :accent="stats.plansEnRetard > 0 ? 'text-[#d03b3b]' : t.accent"
                :delta="stats.plansEnRetard > 0 ? `${stats.plansEnRetard} plan(s) en retard` : null"
                :delta-good="false"
            />
        </div>

        <div class="materio-item card shadow mb-4">
            <div class="materio-item card-body flex flex-col gap-6 sm:flex-row">
                <img
                    v-if="item.photo_url"
                    :src="item.photo_url"
                    :alt="title"
                    class="h-48 w-48 shrink-0 rounded-lg object-cover ring-1 ring-black/10"
                />
                <div
                    v-else
                    class="flex h-48 w-48 shrink-0 items-center justify-center rounded-lg text-sm text-gray-400 ring-1 ring-black/10"
                    :class="t.iconBg"
                >
                    Aucune photo renseignée
                </div>

                <div class="flex-1">
                    <h2 class="text-2xl font-semibold" :class="t.accent">{{ title }}</h2>
                    <div class="mt-2 flex flex-wrap gap-2">
                        <span
                            v-if="item.statut"
                            class="inline-flex rounded-full px-3 py-1 text-xs font-medium"
                            :class="t.badge"
                        >
                            {{ statutLabel(item.statut) }}
                        </span>
                        <span
                            v-if="item.criticite"
                            class="inline-flex rounded-full px-3 py-1 text-xs font-medium"
                            :class="criticiteBadgeClasses[item.criticite]"
                        >
                            Criticité : {{ item.criticite }}
                        </span>
                    </div>

                    <dl class="mt-6 grid gap-x-6 gap-y-4 sm:grid-cols-2">
                        <div v-for="field in fields.filter(f => f.key !== 'statut' && f.key !== 'criticite' && f.key !== 'notes')" :key="field.key">
                            <dt class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ field.label }}</dt>
                            <dd class="mt-1 text-sm text-gray-800 dark:text-gray-200">
                                {{ fieldValue(field) }}
                            </dd>
                        </div>
                    </dl>

                    <div v-if="item.notes" class="mt-6">
                        <dt class="text-xs font-semibold uppercase tracking-wide text-gray-500">Notes</dt>
                        <dd class="mt-1 whitespace-pre-line text-sm text-gray-800 dark:text-gray-200">{{ item.notes }}</dd>
                    </div>
                </div>
            </div>
        </div>

        <!-- Historique des interventions -->
        <div class="materio-item card shadow mb-4">
            <div class="materio-item card-body">
                <div class="mb-4 flex items-center justify-between">
                    <h3 class="text-sm font-semibold" :class="t.accent">Historique des interventions</h3>
                    <Link :href="interventionsUrl" class="text-xs font-medium hover:underline" :class="t.accent">
                        Tout voir &rarr;
                    </Link>
                </div>

                <div v-if="item.interventions?.length" class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700">
                        <thead>
                            <tr class="text-start text-xs font-semibold uppercase tracking-wide text-gray-500">
                                <th class="py-2 pr-4">Type</th>
                                <th class="py-2 pr-4">Statut</th>
                                <th class="py-2 pr-4">Technicien</th>
                                <th class="py-2 pr-4">Date planifiée</th>
                                <th class="py-2 pr-4">Date de fin</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            <tr v-for="intervention in item.interventions" :key="intervention.id">
                                <td class="py-2 pr-4">{{ typeInterventionLabel(intervention.type_intervention) }}</td>
                                <td class="py-2 pr-4">
                                    <span
                                        class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium"
                                        :class="statutInterventionClasses[intervention.statut] ?? 'bg-gray-100 text-gray-600'"
                                    >
                                        {{ statutInterventionLabel(intervention.statut) }}
                                    </span>
                                </td>
                                <td class="py-2 pr-4">{{ intervention.technicien?.name ?? '—' }}</td>
                                <td class="py-2 pr-4">{{ formatDate(intervention.date_planifiee) }}</td>
                                <td class="py-2 pr-4">{{ formatDate(intervention.date_fin) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <p v-else class="text-sm text-gray-500">Aucune intervention enregistrée.</p>
            </div>
        </div>

        <!-- Plans de maintenance préventive -->
        <div v-if="item.plans_maintenance?.length" class="materio-item card shadow mb-4">
            <div class="materio-item card-body">
                <h3 class="mb-4 text-sm font-semibold" :class="t.accent">Plans de maintenance préventive actifs</h3>
                <ul class="divide-y divide-gray-100 dark:divide-gray-800">
                    <li v-for="plan in item.plans_maintenance" :key="plan.id" class="flex items-center justify-between py-2 text-sm">
                        <div>
                            <p class="font-medium text-gray-800 dark:text-gray-200">{{ plan.operation }}</p>
                            <p class="text-xs text-gray-500">
                                Prochaine échéance : {{ formatDate(plan.prochaine_echeance) }}
                            </p>
                        </div>
                        <span
                            v-if="plan.en_retard"
                            class="inline-flex rounded-full bg-red-100 px-2.5 py-0.5 text-xs font-medium text-red-700 dark:bg-red-900 dark:text-red-200"
                        >
                            En retard
                        </span>
                        <span
                            v-else
                            class="inline-flex rounded-full bg-green-100 px-2.5 py-0.5 text-xs font-medium text-green-700 dark:bg-green-900 dark:text-green-200"
                        >
                            À jour
                        </span>
                    </li>
                </ul>
            </div>
        </div>

        <!-- Documents -->
        <div class="materio-item card shadow mb-4">
            <div class="materio-item card-body">
                <h3 class="mb-4 text-sm font-semibold" :class="t.accent">Documents</h3>

                <ul v-if="item.documents?.length" class="mb-4 divide-y divide-gray-200 dark:divide-gray-700">
                    <li v-for="doc in item.documents" :key="doc.id" class="flex items-center justify-between py-2 text-sm">
                        <a :href="doc.url" target="_blank" class="font-medium hover:underline" :class="t.accent">
                            {{ doc.nom_original }}
                        </a>
                        <div class="flex items-center gap-3 text-gray-500">
                            <span>{{ formatSize(doc.taille) }}</span>
                            <button type="button" class="font-medium text-red-600 hover:text-red-800" @click="destroyDocument(doc)">
                                Supprimer
                            </button>
                        </div>
                    </li>
                </ul>
                <p v-else class="mb-4 text-sm text-gray-500">Aucun document renseigné.</p>

                <form @submit.prevent="submitDocuments" class="flex flex-wrap items-center gap-3">
                    <div>
                        <InputLabel for="documents" value="Ajouter des documents (PDF, photos)" />
                        <input
                            id="documents"
                            type="file"
                            multiple
                            accept=".pdf,image/*"
                            @change="onDocumentsChange"
                            class="materio-item form-control-file"
                        />
                        <InputError :message="documentsForm.errors.documents" />
                    </div>
                    <button
                        type="submit"
                        :disabled="documentsForm.processing || documentsForm.documents.length === 0"
                        class="rounded-md px-4 py-2 text-sm font-medium shadow-sm disabled:opacity-50"
                        :class="t.button"
                    >
                        Envoyer
                    </button>
                </form>
            </div>
        </div>
    </div>
</template>
