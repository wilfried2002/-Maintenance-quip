<script setup>
import { ref, watch, nextTick } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import DataTable from '@/Components/DataTable.vue';
import { themes } from '@/moduleTheme';

defineProps({
    fournisseurs: { type: Array, required: true },
});

const t = themes.slate;

const columns = [
    { key: 'nom', label: 'Nom' },
    { key: 'contact_nom', label: 'Contact' },
    { key: 'telephone', label: 'Téléphone' },
    { key: 'email', label: 'Email' },
];

const showForm = ref(false);
const editingId = ref(null);
const firstInput = ref(null);

const emptyValues = () => ({ nom: '', contact_nom: '', telephone: '', email: '', adresse: '', notes: '' });

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
    Object.keys(values).forEach((key) => { values[key] = item[key] ?? ''; });
    form.defaults(values);
    form.reset();
    showForm.value = true;
}

function cancel() {
    showForm.value = false;
    editingId.value = null;
}

watch(showForm, (v) => {
    if (v) {
        nextTick(() => firstInput.value && firstInput.value.focus());
    }
});

function submit() {
    if (editingId.value) {
        form.put(`/fournisseurs/${editingId.value}`, { onSuccess: () => { showForm.value = false; editingId.value = null; } });
    } else {
        form.post('/fournisseurs', { onSuccess: () => { showForm.value = false; } });
    }
}

function destroy(item) {
    if (confirm(`Supprimer le fournisseur « ${item.nom} » ?`)) {
        form.delete(`/fournisseurs/${item.id}`, { preserveScroll: true });
    }
}
</script>

<template>
    <Head title="Fournisseurs" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight" :class="t.accent">Fournisseurs</h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">
                <div class="mb-4">
                    <button
                        type="button"
                        @click="showForm ? cancel() : openCreate()"
                        class="inline-flex items-center rounded-md px-4 py-2 text-sm font-medium shadow-sm"
                        :class="t.button"
                    >
                        {{ showForm ? 'Annuler' : 'Nouveau fournisseur' }}
                    </button>
                </div>
                <transition enter-active-class="transition ease-out duration-200" enter-from-class="opacity-0 translate-y-2" enter-to-class="opacity-100 translate-y-0" leave-active-class="transition ease-in duration-150" leave-from-class="opacity-100 translate-y-0" leave-to-class="opacity-0 -translate-y-2">
                    <div v-if="showForm" class="mb-6 rounded-lg border p-6" :class="t.header">
                        <div class="flex items-center justify-between mb-3">
                            <h3 class="text-sm font-semibold" :class="t.accent">{{ editingId ? 'Modifier le fournisseur' : 'Nouveau fournisseur' }}</h3>
                            <button type="button" class="text-sm text-gray-500 hover:text-gray-700" @click="cancel">Annuler</button>
                        </div>
                        <form @submit.prevent="submit" class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <InputLabel for="nom" value="Nom" />
                                <input id="nom" ref="firstInput" v-model="form.nom" type="text" required placeholder="Nom du fournisseur" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100" :class="t.ring" />
                                <InputError class="mt-1" :message="form.errors.nom" />
                            </div>
                            <div>
                                <InputLabel for="contact_nom" value="Contact" />
                                <input id="contact_nom" v-model="form.contact_nom" type="text" placeholder="Nom du contact" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100" :class="t.ring" />
                            </div>
                            <div>
                                <InputLabel for="telephone" value="Téléphone" />
                                <input id="telephone" v-model="form.telephone" type="text" placeholder="+33 6 12 34 56 78" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100" :class="t.ring" />
                            </div>
                            <div>
                                <InputLabel for="email" value="Email" />
                                <input id="email" v-model="form.email" type="email" placeholder="contact@fournisseur.tld" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100" :class="t.ring" />
                                <InputError class="mt-1" :message="form.errors.email" />
                            </div>
                            <div class="sm:col-span-2">
                                <InputLabel for="adresse" value="Adresse" />
                                <input id="adresse" v-model="form.adresse" type="text" placeholder="Adresse postale" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100" :class="t.ring" />
                            </div>
                            <div class="sm:col-span-2">
                                <InputLabel for="notes" value="Notes" />
                                <textarea id="notes" v-model="form.notes" rows="3" placeholder="Informations complémentaires" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100" :class="t.ring"></textarea>
                            </div>
                            <div class="sm:col-span-2 flex justify-end">
                                <button type="submit" :disabled="form.processing" class="rounded-md px-4 py-2 text-sm font-medium shadow-sm disabled:opacity-50" :class="t.button">Enregistrer</button>
                            </div>
                        </form>
                    </div>
                </transition>

                <div v-if="fournisseurs.length === 0 && !showForm" class="mb-6 rounded-lg border-dashed border-2 border-gray-200 dark:border-gray-700 p-8 text-center">
                    <div class="text-3xl font-bold text-gray-700 dark:text-gray-200">0 résultat</div>
                    <div class="mt-2 text-sm text-gray-500 dark:text-gray-400">Aucun fournisseur pour le moment.</div>
                    <div class="mt-4">
                        <button type="button" @click="openCreate" class="inline-flex items-center rounded-md px-4 py-2 text-sm font-medium shadow-sm" :class="t.button">Créer un fournisseur</button>
                    </div>
                </div>

                <DataTable
                    theme="slate"
                    :columns="columns"
                    :rows="fournisseurs"
                    search-placeholder="Rechercher un fournisseur…"
                    empty-text="Aucun fournisseur pour le moment."
                >
                    <template #cell-nom="{ row }">
                        <span class="font-medium text-gray-800 dark:text-gray-200">{{ row.nom }}</span>
                    </template>

                    <template #actions="{ row }">
                        <button type="button" class="mr-3 font-medium" :class="t.accent" @click="openEdit(row)">Modifier</button>
                        <button type="button" class="font-medium text-red-600 hover:text-red-800" @click="destroy(row)">Supprimer</button>
                    </template>
                </DataTable>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
