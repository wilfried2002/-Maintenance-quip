<script setup>
import { ref, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';

// Toasts globaux : toute redirection avec ->with('status', ...) (succès) ou
// ->with('error', ...) (échec) est affichée ici, une seule fois, quelle que soit
// la page. Les erreurs de validation restent, elles, affichées dans les formulaires.
const page = usePage();
const toasts = ref([]);
let uid = 0;

function push(type, message) {
    if (!message) {
        return;
    }

    const id = ++uid;
    toasts.value.push({ id, type, message });
    // Les erreurs restent plus longtemps à l'écran que les succès.
    setTimeout(() => dismiss(id), type === 'error' ? 7000 : 4500);
}

function dismiss(id) {
    toasts.value = toasts.value.filter((toast) => toast.id !== id);
}

// `page.props.flash` est un objet recréé à CHAQUE réponse Inertia (clôture serveur
// réévaluée par requête) : le watcher se déclenche donc même si le message est
// identique au précédent — deux sauvegardes successives affichent bien deux toasts.
watch(
    () => page.props.flash,
    (flash) => {
        if (!flash) {
            return;
        }
        push('success', flash.status);
        push('error', flash.error);
    },
    { immediate: true },
);
</script>

<template>
    <div class="materio-item position-fixed top-0 end-0 p-3" style="z-index: 2000; pointer-events: none">
        <div
            v-for="toast in toasts"
            :key="toast.id"
            class="materio-item card shadow mb-2 toast-item"
            :class="toast.type === 'error' ? 'border-danger' : 'border-success'"
            style="pointer-events: auto; min-width: 20rem; max-width: 24rem"
            role="status"
        >
            <div class="materio-item card-body d-flex align-items-start gap-2 py-3">
                <i
                    class="materio-item icon-base mt-1"
                    :class="toast.type === 'error'
                        ? 'ri ri-close-circle-line text-danger'
                        : 'ri ri-checkbox-circle-line text-success'"
                ></i>
                <div class="materio-item small flex-grow-1">{{ toast.message }}</div>
                <button
                    type="button"
                    class="materio-item btn btn-sm btn-text p-0 text-body-secondary"
                    aria-label="Fermer"
                    @click="dismiss(toast.id)"
                >
                    <i class="materio-item icon-base ri ri-close-line"></i>
                </button>
            </div>
        </div>
    </div>
</template>

<style scoped>
.toast-item {
    animation: toast-in 0.18s ease-out;
}

@keyframes toast-in {
    from {
        opacity: 0;
        transform: translateY(-6px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}
</style>
