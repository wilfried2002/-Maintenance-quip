<script setup>
import { computed, ref } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';
import DataTable from '@/Components/DataTable.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import { themes } from '@/moduleTheme';

const props = defineProps({
    theme: { type: String, default: 'green' },
    vehicule: { type: Object, required: true },
    releves: { type: Array, required: true },
});

const t = themes[props.theme] ?? themes.slate;
const page = usePage();
const isAdmin = computed(() => page.props.auth.role === 'admin' || page.props.auth.isSuperAdmin);

const afficheForm = ref(false);

const form = useForm({
    kilometrage: props.vehicule.kilometrage_actuel ?? 0,
    date_releve: new Date().toISOString().slice(0, 10),
    note: '',
});

function soumettre() {
    form.post(`/vehicules/${props.vehicule.id}/releves`, {
        preserveScroll: true,
        onSuccess: () => { afficheForm.value = false; form.reset('note'); },
    });
}

function supprimer(releve) {
    if (confirm(`Supprimer le relevé de ${releve.kilometrage} km ?`)) {
        form.delete(`/vehicules/${props.vehicule.id}/releves/${releve.id}`, { preserveScroll: true });
    }
}

const columns = [
    { key: 'date_releve', label: 'Date' },
    { key: 'kilometrage', label: 'Compteur' },
    { key: 'ecart', label: 'Δ depuis le précédent' },
    { key: 'utilisateur', label: 'Par' },
    { key: 'source', label: 'Source' },
    { key: 'note', label: 'Note' },
];

// Δ entre chaque relevé consécutif (liste triée du plus récent au plus ancien).
const lignes = computed(() => props.releves.map((releve, index, tous) => ({
    ...releve,
    utilisateur: releve.utilisateur?.name ?? '—',
    ecart: index < tous.length - 1 ? releve.kilometrage - tous[index + 1].kilometrage : null,
})));

const sourceLabels = {
    saisie: 'Relevé',
    edition_vehicule: 'Fiche véhicule',
};

function nombre(valeur) {
    return Number(valeur ?? 0).toLocaleString('fr-FR');
}

function dateFr(value) {
    return value ? new Date(value).toLocaleDateString('fr-FR') : '—';
}
</script>

<template>
    <div class="materio-item card shadow mb-4">
        <div class="materio-item card-header py-3 d-flex flex-wrap align-items-center justify-content-between gap-2">
            <h6 class="materio-item m-0 fw-bold" :class="t.accent">Relevés kilométriques</h6>
            <button
                type="button"
                class="inline-flex items-center rounded-md px-3 py-1.5 text-sm font-medium shadow-sm"
                :class="t.button"
                @click="afficheForm = !afficheForm"
            >
                {{ afficheForm ? 'Annuler' : 'Nouveau relevé' }}
            </button>
        </div>

        <div class="materio-item card-body">
            <div v-if="afficheForm" class="mb-4 rounded-md bg-gray-50 p-3 dark:bg-gray-800">
                <form @submit.prevent="soumettre" class="materio-item row">
                    <div class="materio-item form-group col-md-3">
                        <InputLabel for="kilometrage" value="Compteur (km)" />
                        <input id="kilometrage" v-model="form.kilometrage" type="number" min="0" required class="materio-item form-control" :class="{ 'is-invalid': form.errors.kilometrage }" />
                        <InputError :message="form.errors.kilometrage" />
                    </div>
                    <div class="materio-item form-group col-md-3">
                        <InputLabel for="date_releve" value="Date du relevé" />
                        <input id="date_releve" v-model="form.date_releve" type="date" required class="materio-item form-control" :class="{ 'is-invalid': form.errors.date_releve }" />
                        <InputError :message="form.errors.date_releve" />
                    </div>
                    <div class="materio-item form-group col-md-6">
                        <InputLabel for="note" value="Note (facultative)" />
                        <input id="note" v-model="form.note" type="text" class="materio-item form-control" placeholder="Ex. : trajet Douala — Yaoundé" />
                    </div>
                    <div class="col-12 flex justify-end">
                        <button type="submit" :disabled="form.processing" class="rounded-md px-4 py-2 text-sm font-medium shadow-sm disabled:opacity-50" :class="t.button">
                            Enregistrer le relevé
                        </button>
                    </div>
                </form>
            </div>

            <p class="materio-item small text-body-secondary mb-3">
                Compteur actuel : <strong>{{ nombre(vehicule.kilometrage_actuel) }} km</strong> —
                chaque lecture est historisée, les plans de maintenance au kilomètre s'appuient sur le dernier relevé.
            </p>

            <DataTable
                :theme="theme"
                :columns="columns"
                :rows="lignes"
                :searchable="false"
                empty-text="Aucun relevé enregistré."
            >
                <template #cell-date_releve="{ row }">
                    {{ dateFr(row.date_releve) }}
                </template>
                <template #cell-kilometrage="{ row }">
                    <span class="font-medium text-gray-800 dark:text-gray-200">{{ nombre(row.kilometrage) }} km</span>
                </template>
                <template #cell-ecart="{ row }">
                    {{ row.ecart === null ? '—' : '+' + nombre(row.ecart) + ' km' }}
                </template>
                <template #cell-source="{ row }">
                    <span class="text-xs text-gray-500">{{ sourceLabels[row.source] ?? row.source }}</span>
                </template>
                <template #actions="{ row }">
                    <button
                        v-if="isAdmin || row.user_id === $page.props.auth.user.id"
                        type="button"
                        class="font-medium text-red-600 hover:text-red-800"
                        @click="supprimer(row)"
                    >
                        Supprimer
                    </button>
                </template>
            </DataTable>
        </div>
    </div>
</template>
