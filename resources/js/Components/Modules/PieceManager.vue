<script setup>
import { ref, computed } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import { themes } from '@/moduleTheme';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import DataTable from '@/Components/DataTable.vue';

const props = defineProps({
    theme: { type: String, required: true },
    pieces: { type: Array, required: true },
    fournisseurs: { type: Array, required: true },
    storeUrl: { type: String, required: true },
    updateUrlBase: { type: String, required: true },
    destroyUrlBase: { type: String, required: true },
    showFormBlock: { type: Boolean, default: true },
    showTable: { type: Boolean, default: true },
});

const t = themes[props.theme] ?? themes.slate;

// Liste paginée côté serveur (paginator Laravel) — compat tableau conservée.
const piecesRows = computed(() =>
    Array.isArray(props.pieces) ? props.pieces : (props.pieces?.data ?? []));
const piecesPaginees = computed(() =>
    Array.isArray(props.pieces) ? null : props.pieces);

// Pré-remplit la recherche du DataTable quand on arrive depuis un résultat de la
// recherche globale (topbar), qui lie vers cette page avec ?q=... — voir GlobalSearch.vue.
const initialSearch = new URLSearchParams(window.location.search).get('q') ?? '';

// Type de module pour les exports PDF (/rapports/liste/{type}/pieces).
const rapportType = props.storeUrl.split('/')[1];

const columns = [
    { key: 'reference', label: 'Référence' },
    { key: 'designation', label: 'Désignation' },
    { key: 'categorie', label: 'Catégorie' },
    { key: 'stock_qte', label: 'Stock' },
    { key: 'prix_unitaire_moyen', label: 'Prix unitaire' },
    { key: 'fournisseur', label: 'Fournisseur' },
];

const showForm = ref(false);
const editingId = ref(null);

const emptyValues = () => ({
    reference: '',
    designation: '',
    categorie: '',
    unite: 'unité',
    stock_qte: 0,
    stock_min: 0,
    prix_unitaire_moyen: '',
    fournisseur_id: '',
    notes: '',
});

const form = useForm(emptyValues());

function openCreate() {
    editingId.value = null;
    form.defaults(emptyValues());
    form.reset();
    showForm.value = true;
}

function openEdit(item) {
    editingId.value = item.id;
    const values = { ...emptyValues() };
    Object.keys(values).forEach((key) => { values[key] = item[key] ?? values[key]; });
    form.defaults(values);
    form.reset();
    showForm.value = true;
}

function cancel() {
    showForm.value = false;
    editingId.value = null;
}

// ─── Mouvement de stock (entrée / sortie / ajustement) ───────────────────────
// Journalisé en base (mouvements_stock) avec le stock résultant ; motif
// obligatoire pour une sortie ou un ajustement d'inventaire.
const mouvementPiece = ref(null);
const mouvementForm = useForm({ type: 'entree', quantite: 1, motif: '' });

const mouvementLabels = {
    entree: 'Entrée (réapprovisionnement)',
    sortie: 'Sortie manuelle',
    ajustement: 'Ajustement (stock physique compté)',
};

function openMouvement(row) {
    mouvementPiece.value = row;
    mouvementForm.defaults({ type: 'entree', quantite: 1, motif: '' });
    mouvementForm.reset();
}

function cancelMouvement() {
    mouvementPiece.value = null;
}

function submitMouvement() {
    mouvementForm.post(`/pieces/${mouvementPiece.value.id}/mouvements`, {
        preserveScroll: true,
        onSuccess: () => { mouvementPiece.value = null; },
    });
}

function submit() {
    if (editingId.value) {
        form.put(`${props.updateUrlBase}/${editingId.value}`, {
            onSuccess: () => { showForm.value = false; editingId.value = null; },
        });
    } else {
        form.post(props.storeUrl, { onSuccess: () => { showForm.value = false; } });
    }
}

function destroy(item) {
    if (confirm(`Supprimer la pièce « ${item.designation} » ?`)) {
        form.delete(`${props.destroyUrlBase}/${item.id}`, { preserveScroll: true });
    }
}

function enSousStock(piece) {
    return piece.stock_qte <= piece.stock_min;
}
</script>

<template>
    <div>
        <div v-if="showFormBlock" class="mb-4 flex flex-wrap items-center gap-3">
            <button
                type="button"
                @click="showForm ? cancel() : openCreate()"
                class="inline-flex items-center rounded-md px-4 py-2 text-sm font-medium shadow-sm"
                :class="t.button"
            >
                {{ showForm ? 'Annuler' : 'Nouvelle pièce' }}
            </button>
            <Link
                :href="route('mouvements-stock.index')"
                class="inline-flex items-center rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800"
            >
                Historique des mouvements
            </Link>
            <a
                :href="`/rapports/liste/${rapportType}/pieces`"
                target="_blank"
                class="inline-flex items-center gap-1 rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800"
            >
                <i class="ri-file-pdf-line"></i> Exporter (PDF)
            </a>
        </div>

        <!-- Mouvement de stock sur une pièce (entrée / sortie / ajustement) -->
        <div v-if="mouvementPiece" class="materio-item card shadow mb-4">
            <div class="materio-item card-header py-3">
                <h6 class="materio-item m-0 fw-bold" :class="t.accent">
                    Mouvement de stock — {{ mouvementPiece.reference }} (stock actuel : {{ mouvementPiece.stock_qte }} {{ mouvementPiece.unite }})
                </h6>
            </div>
            <div class="materio-item card-body">
                <form @submit.prevent="submitMouvement">
                    <div class="materio-item row">
                        <div class="materio-item form-group col-md-4">
                            <InputLabel for="mouvement-type" value="Type de mouvement" />
                            <select id="mouvement-type" v-model="mouvementForm.type" class="materio-item form-control">
                                <option v-for="(label, value) in mouvementLabels" :key="value" :value="value">{{ label }}</option>
                            </select>
                        </div>
                        <div class="materio-item form-group col-md-4">
                            <InputLabel for="mouvement-quantite" value="Quantité" />
                            <input
                                id="mouvement-quantite"
                                v-model="mouvementForm.quantite"
                                type="number"
                                min="0"
                                required
                                class="materio-item form-control"
                                :class="{ 'is-invalid': mouvementForm.errors.quantite }"
                            />
                            <InputError :message="mouvementForm.errors.quantite" />
                        </div>
                        <div class="materio-item form-group col-md-4">
                            <InputLabel for="mouvement-motif" value="Motif" />
                            <input
                                id="mouvement-motif"
                                v-model="mouvementForm.motif"
                                type="text"
                                class="materio-item form-control"
                                :class="{ 'is-invalid': mouvementForm.errors.motif }"
                                :placeholder="mouvementForm.type === 'entree' ? 'Réappro…' : 'Obligatoire'"
                            />
                            <InputError :message="mouvementForm.errors.motif" />
                        </div>
                    </div>
                    <div class="flex justify-end gap-2">
                        <button type="button" class="rounded-md px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300" @click="cancelMouvement">
                            Annuler
                        </button>
                        <button type="submit" :disabled="mouvementForm.processing" class="rounded-md px-4 py-2 text-sm font-medium shadow-sm disabled:opacity-50" :class="t.button">
                            Enregistrer le mouvement
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div v-if="showFormBlock && showForm" class="materio-item card shadow mb-4">
            <div class="materio-item card-header py-3">
                <h6 class="materio-item m-0 fw-bold" :class="t.accent">
                    {{ editingId ? 'Modifier la pièce' : 'Nouvelle pièce' }}
                </h6>
            </div>
            <div class="materio-item card-body">
                <form @submit.prevent="submit">
                    <div class="materio-item row">
                        <div class="materio-item form-group col-md-6">
                            <InputLabel for="reference" value="Référence" />
                            <input id="reference" v-model="form.reference" type="text" required class="materio-item form-control" :class="{ 'is-invalid': form.errors.reference }" />
                            <InputError :message="form.errors.reference" />
                        </div>
                        <div class="materio-item form-group col-md-6">
                            <InputLabel for="designation" value="Désignation" />
                            <input id="designation" v-model="form.designation" type="text" required class="materio-item form-control" :class="{ 'is-invalid': form.errors.designation }" />
                            <InputError :message="form.errors.designation" />
                        </div>
                        <div class="materio-item form-group col-md-6">
                            <InputLabel for="categorie" value="Catégorie" />
                            <input id="categorie" v-model="form.categorie" type="text" class="materio-item form-control" />
                        </div>
                        <div class="materio-item form-group col-md-6">
                            <InputLabel for="unite" value="Unité" />
                            <input id="unite" v-model="form.unite" type="text" class="materio-item form-control" />
                        </div>
                        <div class="materio-item form-group col-md-4">
                            <InputLabel for="stock_qte" value="Stock actuel" />
                            <input id="stock_qte" v-model="form.stock_qte" type="number" min="0" class="materio-item form-control" />
                        </div>
                        <div class="materio-item form-group col-md-4">
                            <InputLabel for="stock_min" value="Seuil d'alerte" />
                            <input id="stock_min" v-model="form.stock_min" type="number" min="0" class="materio-item form-control" />
                        </div>
                        <div class="materio-item form-group col-md-4">
                            <InputLabel for="prix_unitaire_moyen" value="Prix unitaire moyen" />
                            <input id="prix_unitaire_moyen" v-model="form.prix_unitaire_moyen" type="number" step="any" min="0" class="materio-item form-control" />
                        </div>
                        <div class="materio-item form-group col-md-6">
                            <InputLabel for="fournisseur_id" value="Fournisseur" />
                            <select id="fournisseur_id" v-model="form.fournisseur_id" class="materio-item form-select" :class="{ 'is-invalid': form.errors.fournisseur_id }">
                                <option value="">Aucun fournisseur</option>
                                <option v-for="fournisseur in fournisseurs" :key="fournisseur.id" :value="fournisseur.id">
                                    {{ fournisseur.nom }}
                                </option>
                            </select>
                            <InputError :message="form.errors.fournisseur_id" />
                        </div>
                        <div class="materio-item form-group col-12">
                            <InputLabel for="notes" value="Notes" />
                            <textarea id="notes" v-model="form.notes" rows="2" class="materio-item form-control"></textarea>
                        </div>
                    </div>
                    <div class="flex justify-end">
                        <button type="submit" :disabled="form.processing" class="rounded-md px-4 py-2 text-sm font-medium shadow-sm disabled:opacity-50" :class="t.button">
                            Enregistrer
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <DataTable v-if="showTable"
            :theme="theme"
            :columns="columns"
            :rows="piecesRows"
            :paginated="piecesPaginees"
            rows-key="pieces"
            :initial-search="initialSearch"
            search-placeholder="Rechercher une pièce…"
            empty-text="Aucune pièce enregistrée."
        >
            <template #cell-designation="{ row }">
                <span class="font-medium text-gray-800 dark:text-gray-200">{{ row.designation }}</span>
            </template>
            <template #cell-stock_qte="{ row }">
                <span
                    class="inline-flex rounded-full px-2 py-1 text-xs font-medium"
                    :class="enSousStock(row) ? 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200' : t.badge"
                >
                    {{ row.stock_qte }} {{ row.unite }} {{ enSousStock(row) ? '· Stock bas' : '' }}
                </span>
            </template>

            <template #actions="{ row }">
                <button type="button" class="mr-3 font-medium" :class="t.accent" @click="openEdit(row)">Modifier</button>
                <button type="button" class="mr-3 font-medium" :class="t.accent" @click="openMouvement(row)">Mouvement</button>
                <button type="button" class="font-medium text-red-600 hover:text-red-800" @click="destroy(row)">Supprimer</button>
            </template>
        </DataTable>
    </div>
</template>
