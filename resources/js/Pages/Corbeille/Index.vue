<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { themes } from '@/moduleTheme';

defineProps({
    corbeilles: { type: Array, required: true },
});

const t = themes.slate;

const form = useForm({});

function restaurer(element, type) {
    if (confirm(`Restaurer « ${element.libelle} » ?`)) {
        form.post(`/corbeille/${type}/${element.id}/restore`, { preserveScroll: true });
    }
}

function supprimerDefinitivement(element, type) {
    if (confirm(`Supprimer DÉFINITIVEMENT « ${element.libelle} » ? Cette action est irréversible.`)) {
        form.delete(`/corbeille/${type}/${element.id}`, { preserveScroll: true });
    }
}

function dateFr(value) {
    return value ? new Date(value).toLocaleString('fr-FR', { dateStyle: 'short', timeStyle: 'short' }) : '—';
}
</script>

<template>
    <Head title="Corbeille" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight" :class="t.accent">Corbeille</h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <p class="mb-4 text-sm text-gray-600 dark:text-gray-400">
                    Les éléments supprimés sont conservés (suppression douce) et peuvent être restaurés.
                    La suppression définitive est irréversible.
                </p>

                <div v-if="corbeilles.length === 0" class="materio-item card shadow">
                    <div class="materio-item card-body text-center text-body-secondary py-5">
                        La corbeille est vide.
                    </div>
                </div>

                <div v-for="section in corbeilles" :key="section.type" class="materio-item card shadow mb-4">
                    <div class="materio-item card-header py-3">
                        <h6 class="materio-item m-0 fw-bold text-primary">
                            {{ section.label }}
                            <span class="badge text-bg-secondary ms-2">{{ section.elements.length }}</span>
                        </h6>
                    </div>
                    <div class="materio-item card-body">
                        <div class="materio-item table-responsive">
                            <table class="materio-item table table-bordered table-hover" width="100%">
                                <thead class="materio-item table-light">
                                    <tr>
                                        <th class="materio-item">Élément</th>
                                        <th class="materio-item">Supprimé le</th>
                                        <th class="materio-item">Par</th>
                                        <th class="materio-item text-end">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="element in section.elements" :key="element.id">
                                        <td class="materio-item font-medium">{{ element.libelle }}</td>
                                        <td class="materio-item">{{ dateFr(element.deleted_at) }}</td>
                                        <td class="materio-item">{{ element.supprime_par ?? '—' }}</td>
                                        <td class="materio-item text-end text-nowrap">
                                            <button
                                                type="button"
                                                class="mr-3 font-medium text-green-700 hover:text-green-900"
                                                @click="restaurer(element, section.type)"
                                            >
                                                Restaurer
                                            </button>
                                            <button
                                                type="button"
                                                class="font-medium text-red-600 hover:text-red-800"
                                                @click="supprimerDefinitivement(element, section.type)"
                                            >
                                                Supprimer définitivement
                                            </button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
