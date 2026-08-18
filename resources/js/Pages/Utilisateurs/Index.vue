<script setup>
import { ref } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import DataTable from '@/Components/DataTable.vue';
import { themes } from '@/moduleTheme';

const props = defineProps({
    users: { type: Array, required: true },
    roles: { type: Object, required: true },
});

const t = themes.slate;

const columns = [
    { key: 'name', label: 'Nom' },
    { key: 'email', label: 'Email' },
    { key: 'role', label: 'Rôle' },
    { key: 'position', label: 'Fonction' },
    { key: 'is_active', label: 'Statut' },
];

const roleOptions = Object.entries(props.roles).map(([value, label]) => ({ value, label }));

function roleLabel(value) {
    return props.roles[value] ?? value;
}

const showForm = ref(false);
const editingId = ref(null);

const emptyValues = () => ({
    name: '',
    email: '',
    password: '',
    phone: '',
    position: '',
    role: 'user',
    is_active: true,
});

const form = useForm(emptyValues());

function openCreate() {
    editingId.value = null;
    form.defaults(emptyValues());
    form.reset();
    showForm.value = true;
}

function openEdit(user) {
    editingId.value = user.id;
    form.defaults({
        name: user.name,
        email: user.email,
        password: '',
        phone: user.phone ?? '',
        position: user.position ?? '',
        role: user.role,
        is_active: user.is_active,
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
        form.put(`/users/${editingId.value}`, { onSuccess: () => { showForm.value = false; editingId.value = null; } });
    } else {
        form.post('/users', { onSuccess: () => { showForm.value = false; } });
    }
}

function destroy(user) {
    if (confirm(`Retirer « ${user.name} » de l'organisation ?`)) {
        form.delete(`/users/${user.id}`, { preserveScroll: true });
    }
}
</script>

<template>
    <Head title="Utilisateurs" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight" :class="t.accent">Utilisateurs</h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
                <div class="mb-4">
                    <button
                        type="button"
                        @click="showForm ? cancel() : openCreate()"
                        class="inline-flex items-center rounded-md px-4 py-2 text-sm font-medium shadow-sm"
                        :class="t.button"
                    >
                        {{ showForm ? 'Annuler' : 'Nouvel utilisateur' }}
                    </button>
                </div>

                <div v-if="showForm" class="mb-6 rounded-lg border p-6" :class="t.header">
                    <h3 class="mb-4 text-sm font-semibold" :class="t.accent">
                        {{ editingId ? "Modifier l'utilisateur" : 'Nouvel utilisateur' }}
                    </h3>
                    <form @submit.prevent="submit" class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <InputLabel for="name" value="Nom" />
                            <input id="name" v-model="form.name" type="text" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100" :class="t.ring" />
                            <InputError class="mt-1" :message="form.errors.name" />
                        </div>
                        <div>
                            <InputLabel for="email" value="Email" />
                            <input
                                id="email"
                                v-model="form.email"
                                type="email"
                                required
                                :disabled="!!editingId"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm disabled:bg-gray-100 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 dark:disabled:bg-gray-800"
                                :class="t.ring"
                            />
                            <InputError class="mt-1" :message="form.errors.email" />
                        </div>
                        <div v-if="!editingId">
                            <InputLabel for="password" value="Mot de passe" />
                            <input id="password" v-model="form.password" type="password" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100" :class="t.ring" />
                            <InputError class="mt-1" :message="form.errors.password" />
                        </div>
                        <div>
                            <InputLabel for="role" value="Rôle" />
                            <select id="role" v-model="form.role" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100" :class="t.ring" required>
                                <option v-for="opt in roleOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                            </select>
                        </div>
                        <div>
                            <InputLabel for="phone" value="Téléphone" />
                            <input id="phone" v-model="form.phone" type="text" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100" :class="t.ring" />
                        </div>
                        <div>
                            <InputLabel for="position" value="Fonction" />
                            <input id="position" v-model="form.position" type="text" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100" :class="t.ring" />
                        </div>
                        <div v-if="editingId" class="sm:col-span-2">
                            <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                                <input type="checkbox" v-model="form.is_active" class="rounded border-gray-300" />
                                Compte actif dans cette organisation
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
                    :rows="users"
                    search-placeholder="Rechercher un utilisateur…"
                    empty-text="Aucun utilisateur."
                >
                    <template #cell-name="{ row }">
                        <span class="font-medium text-gray-800 dark:text-gray-200">{{ row.name }}</span>
                    </template>
                    <template #cell-role="{ row }">
                        {{ roleLabel(row.role) }}
                    </template>
                    <template #cell-is_active="{ row }">
                        <span
                            class="inline-flex rounded-full px-2 py-1 text-xs font-medium"
                            :class="row.is_active ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300'"
                        >
                            {{ row.is_active ? 'Actif' : 'Inactif' }}
                        </span>
                    </template>

                    <template #actions="{ row }">
                        <button type="button" class="mr-3 font-medium" :class="t.accent" @click="openEdit(row)">Modifier</button>
                        <button type="button" class="font-medium text-red-600 hover:text-red-800" @click="destroy(row)">Retirer</button>
                    </template>
                </DataTable>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
