<script setup>
import { computed, reactive, ref } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';
import { themes, typeInterventionOptions, statutInterventionOptions, prioriteOptions } from '@/moduleTheme';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import DataTable from '@/Components/DataTable.vue';
import { formatMontant } from '@/currency';

const props = defineProps({
    theme: { type: String, required: true },
    interventions: { type: Array, required: true },
    equipements: { type: Array, required: true },
    techniciens: { type: Array, required: true },
    pieces: { type: Array, default: () => [] },
    storeUrl: { type: String, required: true },
    updateUrlBase: { type: String, default: '/interventions' },
    destroyUrlBase: { type: String, default: '/interventions' },
    equipementLabelKey: { type: String, default: 'designation' },
    showFormBlock: { type: Boolean, default: true },
    showTable: { type: Boolean, default: true },
});

const t = themes[props.theme] ?? themes.slate;
const page = usePage();
const devise = page.props.auth.devise;
const isAdmin = computed(() => page.props.auth.role === 'admin' || page.props.auth.isSuperAdmin);

// Liste paginée côté serveur : le contrôleur envoie un paginator Laravel
// ({data, current_page, last_page, total}) — on garde la compatibilité tableau
// simple pour les usages internes (dashboard, preview).
const interventionsRows = computed(() =>
    Array.isArray(props.interventions) ? props.interventions : (props.interventions?.data ?? []));
const interventionsPaginees = computed(() =>
    Array.isArray(props.interventions) ? null : props.interventions);

// Pré-remplit la recherche du DataTable quand on arrive depuis un résultat de la
// recherche globale (topbar), qui lie vers cette page avec ?q=... — voir GlobalSearch.vue.
const initialSearch = new URLSearchParams(window.location.search).get('q') ?? '';

const showForm = ref(false);
const editingId = ref(null);

const columns = [
    { key: 'equipementable', label: 'Équipement', sortable: false },
    { key: 'titre', label: 'Titre' },
    { key: 'statut', label: 'Statut' },
    { key: 'technicien', label: 'Technicien', sortable: false },
    { key: 'date_planifiee', label: 'Date planifiée' },
];

const pieceForm = useForm({ piece_id: '', quantite: 1 });

function addPiece(intervention) {
    pieceForm.post(`/interventions/${intervention.id}/pieces`, {
        preserveScroll: true,
        onSuccess: () => pieceForm.reset(),
    });
}

const removePieceForm = useForm({});

function removePiece(intervention, interventionPiece) {
    removePieceForm.delete(`/interventions/${intervention.id}/pieces/${interventionPiece}`, { preserveScroll: true });
}

function coutPieces(intervention) {
    return (intervention.pieces ?? []).reduce((sum, p) => sum + p.pivot.quantite * p.pivot.prix_unitaire, 0);
}

function coutTotal(intervention) {
    return Number(intervention.cout_main_oeuvre ?? 0) + coutPieces(intervention);
}

// Brouillon de notes par intervention (indépendant de la prop tant que non enregistré).
const notesDrafts = reactive({});

function noteDraft(intervention) {
    if (!(intervention.id in notesDrafts)) {
        notesDrafts[intervention.id] = intervention.notes ?? '';
    }
    return notesDrafts[intervention.id];
}

const notesForm = useForm({ notes: '' });

function saveNotes(intervention) {
    notesForm.notes = notesDrafts[intervention.id] ?? intervention.notes ?? '';
    notesForm.put(`/interventions/${intervention.id}/notes`, { preserveScroll: true });
}

const emptyValues = () => ({
    equipementable_id: '',
    titre: '',
    type_intervention: 'corrective',
    statut: 'planifiee',
    priorite: 'normale',
    date_planifiee: '',
    date_debut: '',
    date_fin: '',
    technicien_id: '',
    description: '',
    cout_main_oeuvre: '',
    duree_heures: '',
    notes: '',
});

const form = useForm(emptyValues());

function dateTimeLocal(value) {
    return value ? String(value).slice(0, 16) : '';
}

function openEdit(intervention) {
    editingId.value = intervention.id;
    const values = {
        ...emptyValues(),
        ...intervention,
        date_planifiee: dateTimeLocal(intervention.date_planifiee),
        date_debut: dateTimeLocal(intervention.date_debut),
        date_fin: dateTimeLocal(intervention.date_fin),
        technicien_id: intervention.technicien_id ?? '',
    };
    form.defaults(values);
    form.reset();
    showForm.value = true;
}

function cancel() {
    editingId.value = null;
    form.defaults(emptyValues());
    form.reset();
    showForm.value = false;
}

function submit() {
    const options = {
        onSuccess: () => {
            cancel();
        },
    };

    if (editingId.value) {
        form.put(`${props.updateUrlBase}/${editingId.value}`, options);
        return;
    }

    form.post(props.storeUrl, options);
}

function destroy(intervention) {
    if (confirm(`Supprimer l'intervention « ${intervention.titre} » ? Les pièces consommées seront remises en stock.`)) {
        form.delete(`${props.destroyUrlBase}/${intervention.id}`, { preserveScroll: true });
    }
}

function equipementLabel(equipement) {
    return `${equipement.code} — ${equipement[props.equipementLabelKey] ?? ''}`;
}

function statutLabel(value) {
    return statutInterventionOptions.find((o) => o.value === value)?.label ?? value;
}
</script>

<template>
    <div>
        <div v-if="showFormBlock" class="mb-4 flex items-center justify-between">
            <button
                type="button"
                @click="showForm ? cancel() : (showForm = true)"
                class="inline-flex items-center rounded-md px-4 py-2 text-sm font-medium shadow-sm transition focus:outline-none focus:ring-2 focus:ring-offset-2"
                :class="t.button"
            >
                {{ showForm ? 'Annuler' : 'Planifier une intervention' }}
            </button>
        </div>

        <div v-if="showFormBlock && showForm" class="materio-item card shadow mb-4">
            <div class="materio-item card-header py-3">
                <h6 class="materio-item m-0 fw-bold" :class="t.accent">{{ editingId ? 'Modifier l’intervention' : 'Nouvelle intervention' }}</h6>
            </div>
            <div class="materio-item card-body">
                <form @submit.prevent="submit">
                    <div class="materio-item row">
                        <div class="materio-item form-group col-12">
                            <InputLabel for="equipementable_id" value="Équipement concerné" />
                            <select
                                id="equipementable_id"
                                v-model="form.equipementable_id"
                                class="materio-item form-control"
                                :class="{ 'is-invalid': form.errors.equipementable_id }"
                                :disabled="Boolean(editingId)"
                                required
                            >
                                <option value="" disabled>Sélectionner…</option>
                                <option v-for="eq in equipements" :key="eq.id" :value="eq.id">
                                    {{ equipementLabel(eq) }}
                                </option>
                            </select>
                            <InputError :message="form.errors.equipementable_id" />
                        </div>

                        <div class="materio-item form-group col-12">
                            <InputLabel for="titre" value="Titre" />
                            <input
                                id="titre"
                                v-model="form.titre"
                                type="text"
                                class="materio-item form-control"
                                :class="{ 'is-invalid': form.errors.titre }"
                                required
                            />
                            <InputError :message="form.errors.titre" />
                        </div>

                        <div class="materio-item form-group col-md-6">
                            <InputLabel for="type_intervention" value="Type" />
                            <select id="type_intervention" v-model="form.type_intervention" class="materio-item form-control">
                                <option v-for="opt in typeInterventionOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                            </select>
                        </div>

                        <div class="materio-item form-group col-md-6">
                            <InputLabel for="statut" value="Statut" />
                            <select id="statut" v-model="form.statut" class="materio-item form-control">
                                <option v-for="opt in statutInterventionOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                            </select>
                        </div>

                        <div class="materio-item form-group col-md-6">
                            <InputLabel for="priorite" value="Priorité" />
                            <select id="priorite" v-model="form.priorite" class="materio-item form-control">
                                <option v-for="opt in prioriteOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                            </select>
                        </div>

                        <div class="materio-item form-group col-md-6">
                            <InputLabel for="technicien_id" value="Technicien assigné" />
                            <select id="technicien_id" v-model="form.technicien_id" class="materio-item form-control">
                                <option value="">—</option>
                                <option v-for="u in techniciens" :key="u.id" :value="u.id">{{ u.name }}</option>
                            </select>
                        </div>

                        <div class="materio-item form-group col-md-6">
                            <InputLabel for="date_planifiee" value="Date planifiée" />
                            <input id="date_planifiee" v-model="form.date_planifiee" type="datetime-local" class="materio-item form-control" />
                        </div>

                        <div class="materio-item form-group col-md-6">
                            <InputLabel for="cout_main_oeuvre" value="Coût main d'œuvre" />
                            <input id="cout_main_oeuvre" v-model="form.cout_main_oeuvre" type="number" step="any" class="materio-item form-control" />
                        </div>

                        <div class="materio-item form-group col-md-6">
                            <InputLabel for="duree_heures" value="Durée (heures)" />
                            <input id="duree_heures" v-model="form.duree_heures" type="number" step="any" class="materio-item form-control" />
                        </div>

                        <div class="materio-item form-group col-12">
                            <InputLabel for="description" value="Description" />
                            <textarea id="description" v-model="form.description" rows="3" class="materio-item form-control"></textarea>
                        </div>

                        <div class="materio-item form-group col-12">
                            <InputLabel for="notes" value="Notes" />
                            <textarea
                                id="notes"
                                v-model="form.notes"
                                rows="2"
                                placeholder="Observations, remarques…"
                                class="materio-item form-control"
                            ></textarea>
                        </div>
                    </div>

                    <div class="flex justify-end">
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="rounded-md px-4 py-2 text-sm font-medium shadow-sm focus:outline-none focus:ring-2 focus:ring-offset-2 disabled:opacity-50"
                            :class="t.button"
                        >
                            {{ editingId ? 'Enregistrer les modifications' : 'Enregistrer l’intervention' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <DataTable v-if="showTable"
            :theme="theme"
            :columns="columns"
            :rows="interventionsRows"
            :paginated="interventionsPaginees"
            rows-key="interventions"
            expandable
            :initial-search="initialSearch"
            search-placeholder="Rechercher une intervention…"
            empty-text="Aucune intervention enregistrée."
        >
            <template #cell-equipementable="{ row }">
                {{ row.equipementable ? equipementLabel(row.equipementable) : '—' }}
            </template>
            <template #cell-statut="{ row }">
                <span class="inline-flex rounded-full px-2 py-1 text-xs font-medium" :class="t.badge">
                    {{ statutLabel(row.statut) }}
                </span>
            </template>
            <template #cell-technicien="{ row }">
                {{ row.technicien?.name ?? '—' }}
            </template>

            <template #actions="{ row, isExpanded, toggleExpand }">
                <div class="flex items-center gap-3">
                    <button type="button" class="inline-flex items-center gap-1 font-medium" :class="t.accent" @click="toggleExpand">
                        Notes &amp; pièces ({{ row.pieces?.length ?? 0 }})
                        <svg class="size-3.5 transition-transform" :class="{ 'rotate-180': isExpanded }" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                        </svg>
                    </button>
                    <a
                        :href="`/interventions/${row.id}/rapport`"
                        target="_blank"
                        class="font-medium text-gray-500 hover:text-gray-700 dark:text-gray-400"
                    >
                        Rapport PDF
                    </a>
                    <template v-if="isAdmin">
                        <button type="button" class="font-medium" :class="t.accent" @click="openEdit(row)">Modifier</button>
                        <button type="button" class="font-medium text-red-600 hover:text-red-800" @click="destroy(row)">Supprimer</button>
                    </template>
                </div>
            </template>

            <template #expanded="{ row }">
                <div class="mb-4">
                    <InputLabel :for="`notes-${row.id}`" value="Notes" />
                    <textarea
                        :id="`notes-${row.id}`"
                        :value="noteDraft(row)"
                        @input="notesDrafts[row.id] = $event.target.value"
                        rows="3"
                        placeholder="Observations, remarques du technicien…"
                        class="materio-item form-control"
                    ></textarea>
                    <div class="mt-2 flex items-center gap-3">
                        <button
                            type="button"
                            :disabled="notesForm.processing"
                            class="rounded-md px-3 py-1.5 text-sm font-medium shadow-sm disabled:opacity-50"
                            :class="t.button"
                            @click="saveNotes(row)"
                        >
                            Enregistrer les notes
                        </button>
                        <InputError :message="notesForm.errors.notes" />
                    </div>
                </div>

                <p class="mb-2 border-t border-gray-200 pt-4 text-xs font-semibold uppercase tracking-wide text-gray-400 dark:border-gray-700">Pièces</p>
                <ul v-if="row.pieces?.length" class="mb-3 divide-y divide-gray-200 text-sm dark:divide-gray-700">
                    <li v-for="p in row.pieces" :key="p.pivot.id" class="flex items-center justify-between py-1.5">
                        <span>{{ p.designation }} — {{ p.pivot.quantite }} × {{ p.pivot.prix_unitaire }}</span>
                        <button type="button" class="text-red-600 hover:text-red-800" @click="removePiece(row, p.pivot.id)">
                            Retirer
                        </button>
                    </li>
                </ul>
                <p v-else class="mb-3 text-sm text-gray-500">Aucune pièce consommée pour le moment.</p>

                <p class="mb-3 text-xs text-gray-500">
                    Coût pièces : {{ formatMontant(coutPieces(row), devise, { decimales: 2 }) }} — Coût total (main d'œuvre + pièces) : {{ formatMontant(coutTotal(row), devise, { decimales: 2 }) }}
                </p>

                <form @submit.prevent="addPiece(row)" class="materio-item row align-items-end">
                    <div class="materio-item form-group col-auto">
                        <InputLabel value="Pièce" />
                        <select v-model="pieceForm.piece_id" class="materio-item form-control" required>
                            <option value="" disabled>Sélectionner…</option>
                            <option v-for="p in pieces" :key="p.id" :value="p.id">
                                {{ p.reference }} — {{ p.designation }} (stock : {{ p.stock_qte }} {{ p.unite }})
                            </option>
                        </select>
                    </div>
                    <div class="materio-item form-group col-auto" style="width: 7rem">
                        <InputLabel value="Quantité" />
                        <input v-model="pieceForm.quantite" type="number" min="1" class="materio-item form-control" />
                    </div>
                    <div class="materio-item form-group col-auto">
                        <button
                            type="submit"
                            :disabled="pieceForm.processing"
                            class="rounded-md px-3 py-1.5 text-sm font-medium shadow-sm disabled:opacity-50"
                            :class="t.button"
                        >
                            Ajouter
                        </button>
                    </div>
                    <InputError :message="pieceForm.errors.quantite ?? pieceForm.errors.piece_id" />
                </form>
            </template>
        </DataTable>
    </div>
</template>
