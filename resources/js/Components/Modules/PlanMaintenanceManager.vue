<script setup>
import { ref, computed } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { themes } from '@/moduleTheme';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import DataTable from '@/Components/DataTable.vue';

const props = defineProps({
    theme: { type: String, required: true },
    plans: { type: Array, required: true },
    equipements: { type: Array, required: true },
    storeUrl: { type: String, required: true },
    updateUrlBase: { type: String, required: true },
    destroyUrlBase: { type: String, required: true },
    executeUrlBase: { type: String, required: true },
    equipementLabelKey: { type: String, default: 'designation' },
    allowKilometres: { type: Boolean, default: false },
    showFormBlock: { type: Boolean, default: true },
    showTable: { type: Boolean, default: true },
});

const t = themes[props.theme] ?? themes.slate;

// Liste paginée côté serveur (paginator Laravel) — compat tableau conservée.
const plansRows = computed(() =>
    Array.isArray(props.plans) ? props.plans : (props.plans?.data ?? []));
const plansPaginees = computed(() =>
    Array.isArray(props.plans) ? null : props.plans);

const showForm = ref(false);
const editingId = ref(null);

const emptyValues = () => ({
    equipementable_id: '',
    operation: '',
    type_frequence: 'jours',
    frequence_valeur: '',
    actif: true,
    notes: '',
});

const form = useForm(emptyValues());

function openCreate() {
    editingId.value = null;
    form.defaults(emptyValues());
    form.reset();
    showForm.value = true;
}

function openEdit(plan) {
    editingId.value = plan.id;
    form.defaults({
        equipementable_id: plan.equipementable_id,
        operation: plan.operation,
        type_frequence: plan.type_frequence,
        frequence_valeur: plan.frequence_valeur,
        actif: plan.actif,
        notes: plan.notes ?? '',
    });
    form.reset();
    showForm.value = true;
}

function cancel() {
    showForm.value = false;
    editingId.value = null;
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

function destroy(plan) {
    if (confirm(`Supprimer le plan « ${plan.operation} » ?`)) {
        form.delete(`${props.destroyUrlBase}/${plan.id}`, { preserveScroll: true });
    }
}

const executeForm = useForm({});

function markExecuted(plan) {
    executeForm.post(`${props.executeUrlBase}/${plan.id}/executer`, { preserveScroll: true });
}

function equipementLabel(equipement) {
    return `${equipement.code} — ${equipement[props.equipementLabelKey] ?? ''}`;
}

function frequenceLabel(plan) {
    return plan.type_frequence === 'jours'
        ? `Tous les ${plan.frequence_valeur} jours`
        : `Tous les ${plan.frequence_valeur} km`;
}

function echeanceLabel(plan) {
    if (plan.type_frequence === 'jours') {
        return plan.prochaine_echeance ? new Date(plan.prochaine_echeance).toLocaleDateString('fr-FR') : '—';
    }
    const km = plan.equipementable?.kilometrage_actuel ?? 0;
    const depuis = km - (plan.derniere_execution_km ?? 0);
    return `${Math.max(depuis, 0)} / ${plan.frequence_valeur} km parcourus`;
}

const columns = [
    { key: 'equipementable', label: 'Équipement', sortable: false },
    { key: 'operation', label: 'Opération' },
    { key: 'frequence_valeur', label: 'Fréquence' },
    // Attribut calculé côté PHP : non triable en base.
    { key: 'prochaine_echeance', label: 'Échéance', sortable: false },
    { key: 'actif', label: 'Statut', sortable: false },
];
</script>

<template>
    <div>
        <div v-if="showFormBlock" class="mb-4 flex items-center justify-between">
            <button
                type="button"
                @click="showForm ? cancel() : openCreate()"
                class="inline-flex items-center rounded-md px-4 py-2 text-sm font-medium shadow-sm"
                :class="t.button"
            >
                {{ showForm ? 'Annuler' : (editingId ? 'Annuler' : 'Nouveau plan') }}
            </button>
        </div>

        <div v-if="showFormBlock && showForm" class="materio-item card shadow mb-4">
            <div class="materio-item card-header py-3">
                <h6 class="materio-item m-0 fw-bold" :class="t.accent">
                    {{ editingId ? 'Modifier le plan' : 'Nouveau plan de maintenance préventive' }}
                </h6>
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
                                required
                            >
                                <option value="" disabled>Sélectionner…</option>
                                <option v-for="eq in equipements" :key="eq.id" :value="eq.id">{{ equipementLabel(eq) }}</option>
                            </select>
                            <InputError :message="form.errors.equipementable_id" />
                        </div>

                        <div class="materio-item form-group col-12">
                            <InputLabel for="operation" value="Opération" />
                            <input
                                id="operation"
                                v-model="form.operation"
                                type="text"
                                placeholder="Ex : Vidange moteur, contrôle électrique…"
                                class="materio-item form-control"
                                :class="{ 'is-invalid': form.errors.operation }"
                                required
                            />
                            <InputError :message="form.errors.operation" />
                        </div>

                        <div class="materio-item form-group col-md-6">
                            <InputLabel for="type_frequence" value="Fréquence" />
                            <select id="type_frequence" v-model="form.type_frequence" class="materio-item form-control">
                                <option value="jours">Jours</option>
                                <option v-if="allowKilometres" value="kilometres">Kilomètres</option>
                            </select>
                        </div>

                        <div class="materio-item form-group col-md-6">
                            <InputLabel for="frequence_valeur" :value="form.type_frequence === 'jours' ? 'Tous les combien de jours' : 'Tous les combien de km'" />
                            <input
                                id="frequence_valeur"
                                v-model="form.frequence_valeur"
                                type="number"
                                min="1"
                                class="materio-item form-control"
                                :class="{ 'is-invalid': form.errors.frequence_valeur }"
                                required
                            />
                            <InputError :message="form.errors.frequence_valeur" />
                        </div>

                        <div class="materio-item form-group col-12">
                            <div class="materio-item form-check">
                                <input id="plan_actif" type="checkbox" v-model="form.actif" class="materio-item form-check-input" />
                                <label class="materio-item form-check-label" for="plan_actif">Plan actif</label>
                            </div>
                        </div>

                        <div class="materio-item form-group col-12">
                            <InputLabel for="notes" value="Notes" />
                            <textarea id="notes" v-model="form.notes" rows="2" class="materio-item form-control"></textarea>
                        </div>
                    </div>

                    <div class="flex justify-end">
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="rounded-md px-4 py-2 text-sm font-medium shadow-sm disabled:opacity-50"
                            :class="t.button"
                        >
                            Enregistrer
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <DataTable v-if="showTable"
            :theme="theme"
            :columns="columns"
            :rows="plansRows"
            :paginated="plansPaginees"
            rows-key="plans"
            search-placeholder="Rechercher un plan…"
            empty-text="Aucun plan de maintenance préventive."
        >
            <template #cell-equipementable="{ row }">
                {{ row.equipementable ? equipementLabel(row.equipementable) : '—' }}
            </template>
            <template #cell-operation="{ row }">
                <span class="font-medium text-gray-800 dark:text-gray-200">{{ row.operation }}</span>
            </template>
            <template #cell-frequence_valeur="{ row }">
                {{ frequenceLabel(row) }}
            </template>
            <template #cell-prochaine_echeance="{ row }">
                {{ echeanceLabel(row) }}
            </template>
            <template #cell-actif="{ row }">
                <span v-if="!row.actif" class="inline-flex rounded-full bg-gray-100 px-2 py-1 text-xs font-medium text-gray-600 dark:bg-gray-700 dark:text-gray-300">
                    Inactif
                </span>
                <span v-else-if="row.en_retard" class="inline-flex rounded-full bg-red-100 px-2 py-1 text-xs font-medium text-red-800 dark:bg-red-900 dark:text-red-200">
                    En retard
                </span>
                <span v-else class="inline-flex rounded-full bg-green-100 px-2 py-1 text-xs font-medium text-green-800 dark:bg-green-900 dark:text-green-200">
                    À jour
                </span>
            </template>

            <template #actions="{ row }">
                <button type="button" class="mr-3 font-medium" :class="t.accent" @click="markExecuted(row)">Marquer exécuté</button>
                <button type="button" class="mr-3 font-medium" :class="t.accent" @click="openEdit(row)">Modifier</button>
                <button type="button" class="font-medium text-red-600 hover:text-red-800" @click="destroy(row)">Supprimer</button>
            </template>
        </DataTable>
    </div>
</template>
