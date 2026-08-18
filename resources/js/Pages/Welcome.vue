<script setup>
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    canLogin: {
        type: Boolean,
    },
    canRegister: {
        type: Boolean,
    },
});
</script>

<template>
    <Head title="Accueil" />
    <div class="materio-page">
    <div class="d-flex min-vh-100 flex-column">
        <div class="container">
            <header class="d-flex align-items-center justify-content-between py-5">
                <div class="d-flex align-items-center gap-2">
                    <span class="app-brand-logo demo text-primary">
                        <i class="icon-base ri ri-tools-fill icon-32px"></i>
                    </span>
                    <span class="fw-semibold">Maintenance Équip.</span>
                </div>

                <nav v-if="canLogin" class="d-flex gap-2">
                    <Link
                        v-if="$page.props.auth.user"
                        :href="route('dashboard')"
                        class="btn btn-outline-primary btn-sm"
                    >
                        Tableau de bord
                    </Link>

                    <template v-else>
                        <Link :href="route('login')" class="btn btn-outline-primary btn-sm">
                            Se connecter
                        </Link>
                        <Link v-if="canRegister" :href="route('register')" class="btn btn-primary btn-sm">
                            Inscription
                        </Link>
                    </template>
                </nav>
            </header>

            <main class="text-center py-6">
                <h1 class="mb-3">Gestion de Maintenance Assistée par Ordinateur</h1>
                <p class="text-body-secondary mx-auto mb-5" style="max-width: 42rem">
                    Un seul outil pour piloter vos équipements industriels, votre parc automobile et votre
                    équipement de bureau : interventions, pièces, coûts d'entretien et indicateurs de performance.
                </p>

                <Link
                    :href="$page.props.auth.user ? route('dashboard') : route('login')"
                    class="btn btn-primary"
                >
                    {{ $page.props.auth.user ? 'Accéder au tableau de bord' : 'Se connecter' }}
                </Link>
            </main>
        </div>

        <footer class="mt-auto py-5 text-center text-body-secondary small">
            Maintenance Équipement Industriel
        </footer>
    </div>
    </div>
</template>
