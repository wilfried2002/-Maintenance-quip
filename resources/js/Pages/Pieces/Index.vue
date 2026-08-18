<script setup>
import { Head } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import StatTile from '@/Components/Charts/StatTile.vue';

const modules = [
    {
        label: 'Équipements industriels',
        value: 'Gérer le stock',
        icon: 'fa-industry',
        variant: 'warning',
        href: route('equipements-industriels.pieces.index'),
    },
    {
        label: 'Parc automobile',
        value: 'Gérer le stock',
        icon: 'fa-truck',
        variant: 'success',
        href: route('vehicules.pieces.index'),
    },
    {
        label: 'Équipement de bureau',
        value: 'Gérer le stock',
        icon: 'fa-desktop',
        variant: 'info',
        href: route('equipements-bureau.pieces.index'),
    },
];
</script>

<template>
    <Head title="Pièces détachées / Stock" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                Pièces détachées / Stock
            </h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <p class="mb-6 text-sm text-gray-600 dark:text-gray-400">
                    Le stock de pièces est propre à chaque module : choisissez un module pour consulter
                    et gérer ses pièces détachées.
                </p>

                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <StatTile
                        v-for="mod in modules"
                        :key="mod.href"
                        :label="mod.label"
                        :value="mod.value"
                        :icon="mod.icon"
                        :variant="mod.variant"
                        :href="mod.href"
                    />
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
