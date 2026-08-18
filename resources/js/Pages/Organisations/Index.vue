<script setup>
import { ref } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import DataTable from '@/Components/DataTable.vue';
import { themes } from '@/moduleTheme';
import { deviseOptions, symboleDevise } from '@/currency';

defineProps({
    organisations: { type: Array, required: true },
});

const t = themes.slate;

const columns = [
    { key: 'name', label: 'Nom' },
    { key: 'code', label: 'Code' },
    { key: 'devise', label: 'Devise' },
    { key: 'users_count', label: 'Utilisateurs' },
    { key: 'is_active', label: 'Statut' },
];

const showForm = ref(false);
const editingId = ref(null);

const emptyValues = () => ({ name: '', code: '', devise: 'XOF', is_active: true });

const form = useForm(emptyValues());

function openCreate() {
    editingId.value = null;
    form.defaults(emptyValues());
    form.reset();
    showForm.value = true;
}

function openEdit(organisation) {
    editingId.value = organisation.id;
    form.defaults({
        name: organisation.name,
        code: organisation.code,
        devise: organisation.devise,
        is_active: organisation.is_active,
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
        form.put(`/organisations/${editingId.value}`, { onSuccess: () => { showForm.value = false; editingId.value = null; } });
    } else {
        form.post('/organisations', { onSuccess: () => { showForm.value = false; } });
    }
}
</script>

<template>
    <Head title="Organisations" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight" :class="t.accent">Organisations</h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">
                <p class="mb-6 text-sm text-gray-500 dark:text-gray-400">
                    Chaque organisation est cloisonnée : ses équipements, interventions, pièces et coûts ne sont
                    visibles que par ses propres utilisateurs (connectés avec son code organisation).
                </p>

                <div class="mb-4">
                    <button
                        type="button"
                        @click="showForm ? cancel() : openCreate()"
                        class="inline-flex items-center rounded-md px-4 py-2 text-sm font-medium shadow-sm"
                        :class="t.button"
                    >
                        {{ showForm ? 'Annuler' : 'Nouvelle organisation' }}
                    </button>
                </div>

                <div v-if="showForm" class="mb-6 rounded-lg border p-6" :class="t.header">
                    <h3 class="mb-4 text-sm font-semibold" :class="t.accent">
                        {{ editingId ? "Modifier l'organisation" : 'Nouvelle organisation' }}
                    </h3>
                    <form @submit.prevent="submit" class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <InputLabel for="name" value="Nom" />
                            <input id="name" v-model="form.name" type="text" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100" :class="t.ring" />
                            <InputError class="mt-1" :message="form.errors.name" />
                        </div>
                        <div>
                            <InputLabel for="code" value="Code organisation" />
                            <input id="code" v-model="form.code" type="text" required placeholder="Ex : DEMO01" class="mt-1 block w-full rounded-md border-gray-300 uppercase shadow-sm dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100" :class="t.ring" />
                            <InputError class="mt-1" :message="form.errors.code" />
                            <p class="mt-1 text-xs text-gray-500">Code saisi par les utilisateurs de cette organisation à la connexion.</p>
                        </div>
                        <div>
                            <InputLabel for="devise" value="Devise" />
                            <select
                                id="devise"
                                v-model="form.devise"
                                :disabled="!!editingId"
                                required
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm disabled:bg-gray-100 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 dark:disabled:bg-gray-800"
                                :class="t.ring"
                            >
                                <option v-for="opt in deviseOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                            </select>
                            <InputError class="mt-1" :message="form.errors.devise" />
                            <p class="mt-1 text-xs text-gray-500">Choisie à la création, fixe ensuite.</p>
                        </div>
                        <div v-if="editingId" class="sm:col-span-2">
                            <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                                <input type="checkbox" v-model="form.is_active" class="rounded border-gray-300" />
                                Organisation active (les comptes désactivés ne peuvent plus se connecter)
                            </label>
                        </div>
                        <div class="sm:col-span-2 flex justify-end">
                            <button type="submit" :disabled="form.processing" class="rounded-md px-4 py-2 text-sm font-medium shadow-sm disabled:opacity-50" :class="t.button">
                                Enregistrer
                            </button>
                        </div>
                    </form>
                </div>

                <DataTable
                    theme="slate"
                    :columns="columns"
                    :rows="organisations"
                    search-placeholder="Rechercher une organisation…"
                    empty-text="Aucune organisation."
                >
                    <template #cell-name="{ row }">
                        <span class="font-medium text-gray-800 dark:text-gray-200">{{ row.name }}</span>
                    </template>
                    <template #cell-code="{ row }">
                        <span class="font-mono text-xs">{{ row.code }}</span>
                    </template>
                    <template #cell-devise="{ row }">
                        {{ row.devise }} ({{ symboleDevise(row.devise) }})
                    </template>
                    <template #cell-is_active="{ row }">
                        <span
                            class="inline-flex rounded-full px-2 py-1 text-xs font-medium"
                            :class="row.is_active ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300'"
                        >
                            {{ row.is_active ? 'Active' : 'Désactivée' }}
                        </span>
                    </template>

                    <template #actions="{ row }">
                        <button type="button" class="font-medium" :class="t.accent" @click="openEdit(row)">Modifier</button>
                    </template>
                </DataTable>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
