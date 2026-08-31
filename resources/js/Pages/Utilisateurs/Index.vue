<script setup>
import { computed, ref } from 'vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import DataTable from '@/Components/DataTable.vue';
import { themes } from '@/moduleTheme';

const props = defineProps({
    users: { type: Array, required: true },
    roles: { type: Object, required: true },
    roleDefaults: { type: Object, default: () => ({}) },
});

const page = usePage();
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

// ─── Grille de permissions par module ────────────────────────────────────────
// Pour chaque module : « def » (défaut du rôle, aucune ligne en base), « granted »
// (accordé au-delà du rôle) ou « revoked » (révoqué malgré le rôle). Le rôle
// « admin » passe toujours outre : la grille n'a pas d'effet sur lui.
const moduleDefs = computed(() => page.props.moduleDefs ?? {});
const permissionOptions = [
    { value: 'def', label: 'Défaut (selon le rôle)' },
    { value: 'granted', label: 'Accordé' },
    { value: 'revoked', label: 'Révoqué' },
];

const permissionsUserId = ref(null);
const permissionsForm = useForm({ permissions: {} });
// Brouillon local : { module: 'def' | 'granted' | 'revoked' }
const permissionsDraft = ref({});

function etatEffectif(user, moduleKey) {
    // Défaut du rôle courant de l'utilisateur (grille pré-cochée à l'ouverture).
    const defaut = (props.roleDefaults[user.role] ?? []).includes(moduleKey) ? 'granted' : 'revoked';
    const override = user.permissions?.[moduleKey];
    return override === undefined ? 'def' : (override ? 'granted' : 'revoked');
}

function openPermissions(user) {
    permissionsUserId.value = user.id;
    const draft = {};
    Object.keys(moduleDefs.value).forEach((key) => {
        draft[key] = etatEffectif(user, key);
    });
    permissionsDraft.value = draft;
}

function cancelPermissions() {
    permissionsUserId.value = null;
}

function submitPermissions() {
    permissionsForm.permissions = { ...permissionsDraft.value };
    permissionsForm.put(`/users/${permissionsUserId.value}/permissions`, {
        preserveScroll: true,
        onSuccess: () => {
            permissionsUserId.value = null;
        },
    });
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

                <!-- Grille de permissions par module (overrides au-delà du rôle) -->
                <div v-if="permissionsUserId" class="mb-6 rounded-lg border p-6" :class="t.header">
                    <h3 class="mb-1 text-sm font-semibold" :class="t.accent">
                        Permissions par module — {{ users.find((u) => u.id === permissionsUserId)?.name }}
                    </h3>
                    <p class="mb-4 text-xs text-gray-600 dark:text-gray-400">
                        « Défaut » suit le rôle de l'utilisateur ; « Accordé » / « Révoqué » crée un
                        override propre à cette organisation, quel que soit son rôle.
                        Sans effet sur le rôle Administrateur, qui a toujours accès à tout.
                    </p>
                    <InputError class="mb-4" :message="permissionsForm.errors.permissions" />
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div v-for="(def, key) in moduleDefs" :key="key" class="flex items-center justify-between gap-3 rounded-md border border-gray-200 px-3 py-2 dark:border-gray-700">
                            <div class="min-w-0">
                                <div class="truncate text-sm font-medium text-gray-800 dark:text-gray-200">{{ def.label }}</div>
                                <div class="text-xs text-gray-500">
                                    Défaut {{ (roleDefaults[users.find((u) => u.id === permissionsUserId)?.role] ?? []).includes(key) ? '✓ accordé' : '✗ refusé' }}
                                    pour ce rôle
                                </div>
                            </div>
                            <select v-model="permissionsDraft[key]" class="rounded-md border-gray-300 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100" :class="t.ring">
                                <option v-for="opt in permissionOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                            </select>
                        </div>
                    </div>
                    <div class="mt-4 flex justify-end gap-2">
                        <button type="button" class="rounded-md px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300" @click="cancelPermissions">
                            Annuler
                        </button>
                        <button type="button" :disabled="permissionsForm.processing" class="rounded-md px-4 py-2 text-sm font-medium shadow-sm disabled:opacity-50" :class="t.button" @click="submitPermissions">
                            Enregistrer les permissions
                        </button>
                    </div>
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
                        <button type="button" class="mr-3 font-medium" :class="t.accent" @click="openPermissions(row)">Permissions</button>
                        <button type="button" class="font-medium text-red-600 hover:text-red-800" @click="destroy(row)">Retirer</button>
                    </template>
                </DataTable>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
