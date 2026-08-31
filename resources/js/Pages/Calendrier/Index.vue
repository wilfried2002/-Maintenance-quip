<script setup>
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { themes } from '@/moduleTheme';

const props = defineProps({
    evenements: { type: Array, required: true },
    mois: { type: String, required: true }, // YYYY-MM
    modules: { type: Object, required: true },
});

const t = themes.slate;

const annee = computed(() => parseInt(props.mois.slice(0, 4), 10));
const numeroMois = computed(() => parseInt(props.mois.slice(5, 7), 10)); // 1-12

const nomMois = computed(() =>
    new Date(annee.value, numeroMois.value - 1, 1).toLocaleDateString('fr-FR', { month: 'long', year: 'numeric' }));

function decaler(delta) {
    const date = new Date(annee.value, numeroMois.value - 1 + delta, 1);
    return `/calendrier?mois=${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}`;
}

// Grille : cases vides jusqu'au 1ᵉʳ jour (lundi en tête) puis les jours du mois.
const cellules = computed(() => {
    const premierJour = new Date(annee.value, numeroMois.value - 1, 1);
    const nbJours = new Date(annee.value, numeroMois.value, 0).getDate();
    // getDay() : dimanche = 0 → lundi = 1 ; on veut lundi en colonne 0.
    const decalage = (premierJour.getDay() + 6) % 7;

    const parDate = new Map();
    for (const evenement of props.evenements) {
        if (!evenement.date) continue;
        if (!parDate.has(evenement.date)) parDate.set(evenement.date, []);
        parDate.get(evenement.date).push(evenement);
    }

    const aujourdHui = new Date().toISOString().slice(0, 10);
    const cellulesJour = [];

    for (let jour = 1; jour <= nbJours; jour++) {
        const cle = `${props.mois}-${String(jour).padStart(2, '0')}`;
        cellulesJour.push({
            jour,
            cle,
            aujourdhui: cle === aujourdHui,
            evenements: parDate.get(cle) ?? [],
        });
    }

    return [...Array(decalage).fill(null), ...cellulesJour];
});

const jourSemaine = ['Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim'];

const statutLabels = {
    planifiee: 'Planifiée',
    en_cours: 'En cours',
    terminee: 'Terminée',
    annulee: 'Annulée',
};
</script>

<template>
    <Head title="Calendrier des interventions" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight capitalize" :class="t.accent">{{ nomMois }}</h2>
                <div class="flex items-center gap-2">
                    <Link :href="decaler(-1)" class="rounded-md border border-gray-300 px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800">← Mois précédent</Link>
                    <Link href="/calendrier" class="rounded-md border border-gray-300 px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800">Aujourd'hui</Link>
                    <Link :href="decaler(1)" class="rounded-md border border-gray-300 px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800">Mois suivant →</Link>
                </div>
            </div>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="mb-3 flex flex-wrap items-center gap-3 text-xs text-gray-600 dark:text-gray-400">
                    <span v-for="(label, key) in modules" :key="key" class="inline-flex items-center gap-1">
                        <span class="h-2.5 w-2.5 rounded-full bg-blue-600"></span> {{ label }}
                    </span>
                    <span class="ml-2">Statuts :</span>
                    <span class="inline-flex items-center gap-1"><span class="h-2.5 w-2.5 rounded-full bg-blue-600"></span>Planifiée</span>
                    <span class="inline-flex items-center gap-1"><span class="h-2.5 w-2.5 rounded-full bg-yellow-500"></span>En cours</span>
                    <span class="inline-flex items-center gap-1"><span class="h-2.5 w-2.5 rounded-full bg-green-600"></span>Terminée</span>
                    <span class="inline-flex items-center gap-1"><span class="h-2.5 w-2.5 rounded-full bg-gray-400"></span>Annulée</span>
                </div>

                <div class="overflow-hidden rounded-lg ring-1 ring-black/10 dark:ring-white/10">
                    <div class="grid grid-cols-7 bg-gray-100 text-center text-xs font-semibold uppercase text-gray-600 dark:bg-gray-800 dark:text-gray-300">
                        <div v-for="jour in jourSemaine" :key="jour" class="px-2 py-2">{{ jour }}</div>
                    </div>
                    <div class="grid grid-cols-7">
                        <div
                            v-for="(cellule, index) in cellules"
                            :key="index"
                            class="min-h-24 border-b border-r border-gray-200 p-1 dark:border-gray-700"
                            :class="cellule?.aujourdhui ? 'bg-yellow-50 dark:bg-yellow-900/20' : 'bg-white dark:bg-gray-900'"
                        >
                            <template v-if="cellule">
                                <div class="mb-1 text-right text-xs font-medium" :class="cellule.aujourdhui ? 'text-yellow-700' : 'text-gray-400'">
                                    {{ cellule.jour }}
                                </div>
                                <div class="space-y-1">
                                    <div
                                        v-for="evenement in cellule.evenements"
                                        :key="evenement.id"
                                        class="truncate rounded px-1.5 py-0.5 text-xs text-white"
                                        :class="evenement.couleur"
                                        :title="`${evenement.titre} — ${evenement.equipement ?? ''} (${statutLabels[evenement.statut] ?? evenement.statut})`"
                                    >
                                        {{ evenement.titre }}
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
