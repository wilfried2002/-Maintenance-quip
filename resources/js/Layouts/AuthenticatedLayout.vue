<script setup>
import { computed, onMounted, onUnmounted } from 'vue';
import { usePage, Link, router } from '@inertiajs/vue3';
import NotificationBell from '@/Components/NotificationBell.vue';
import GlobalSearch from '@/Components/GlobalSearch.vue';
import FlashToasts from '@/Components/FlashToasts.vue';

const page = usePage();

// Modules accessibles à l'utilisateur connecté (rôle + éventuels overrides), avec une
// route de menu déclarée — voir HandleInertiaRequests et config/modules.php. Les icônes
// (m.icon, ex. "ri-building-4-line") viennent directement de config/modules.php : classes
// Remix Icon (thème Materio), résolues via le CSS iconify auto-hébergé.
const moduleLinks = computed(() => {
    const accessible = page.props.auth.accessibleModules ?? [];
    const defs = page.props.moduleDefs ?? {};

    return Object.entries(defs)
        .filter(([key, def]) => def.route && accessible.includes(key))
        .map(([key, def]) => ({ key, ...def }));
});

const sections = computed(() => {
    const byKey = Object.fromEntries(moduleLinks.value.map((m) => [m.key, m]));
    const groups = [
        { title: 'Équipements', keys: ['equipements_industriels', 'parc_automobile', 'equipement_bureau'] },
        { title: 'Approvisionnement', keys: ['pieces_stock', 'fournisseurs'] },
        { title: 'Pilotage', keys: ['couts_entretien', 'indicateurs'] },
        { title: 'Administration', keys: ['utilisateurs'] },
    ];
    return groups
        .map((g) => ({ title: g.title, items: g.keys.map((k) => byKey[k]).filter(Boolean) }))
        .filter((g) => g.items.length > 0);
});

const organisationSwitcher = computed(() => page.props.organisationSwitcher);

function switchOrganisation(event) {
    router.post(route('organisation.switch'), { organisation_id: event.target.value }, { preserveScroll: true });
}

function isActive(routeName) {
    return route().current(`${routeName.split('.')[0]}.*`);
}

function initials(name) {
    return (name ?? '')
        .split(' ')
        .map((p) => p[0])
        .slice(0, 2)
        .join('')
        .toUpperCase();
}

// --- Sidebar collapse : délégué à window.Helpers (public/vendor/materio/js/helpers.js,
// chargé globalement dans app.blade.php) plutôt que ré-implémenté — c'est exactement son
// rôle (bascule les classes body.layout-menu-collapsed / expanded, gère mobile vs desktop).
function toggleSidebar() {
    window.Helpers?.toggleCollapsed();
}

onMounted(() => {
    if (!window.Helpers?.isSmallScreen?.()) {
        window.Helpers?.setCollapsed(true, false);
    }
    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach((el) => new window.bootstrap.Tooltip(el));
});
onUnmounted(() => {
    document.body.classList.remove('layout-menu-collapsed', 'layout-menu-expanded');
});

const year = new Date().getFullYear();
</script>

<template>
    <div class="materio-item layout-wrapper layout-content-navbar">
        <!-- Toasts globaux (succès/erreur flashés par le serveur) -->
        <FlashToasts />

        <div class="materio-item layout-container">
            <!-- Sidebar -->
            <aside id="layout-menu" class="materio-item layout-menu menu-vertical menu bg-menu-theme">
                <div class="materio-item app-brand demo">
                    <Link class="materio-item app-brand-link" :href="route('dashboard')">
                        <span class="materio-item app-brand-logo demo text-primary">
                            <i class="materio-item icon-base ri ri-tools-fill icon-32px"></i>
                        </span>
                        <span class="materio-item app-brand-text demo menu-text fw-semibold ms-2">Maintenance Équip.</span>
                    </Link>
                    <a href="javascript:void(0)" class="materio-item layout-menu-toggle menu-link text-large ms-auto" @click="toggleSidebar">
                        <i class="materio-item icon-base ri ri-radio-button-line d-block d-xl-none align-middle"></i>
                        <i class="materio-item icon-base ri ri-record-circle-line d-none d-xl-block align-middle"></i>
                    </a>
                </div>

                <div class="materio-item menu-inner-shadow"></div>

                <ul class="materio-item menu-inner py-1">
                    <li class="materio-item menu-item" :class="{ active: route().current('dashboard') }">
                        <Link class="materio-item menu-link" :href="route('dashboard')">
                            <i class="materio-item menu-icon icon-base ri ri-dashboard-line"></i>
                            <div>Tableau de bord</div>
                        </Link>
                    </li>

                    <template v-for="section in sections" :key="section.title">
                        <li class="materio-item menu-header mt-5">
                            <span class="materio-item menu-header-text">{{ section.title }}</span>
                        </li>
                        <li v-for="m in section.items" :key="m.key" class="materio-item menu-item" :class="{ active: isActive(m.route) }">
                            <Link class="materio-item menu-link" :href="route(m.route)">
                                <i class="materio-item menu-icon icon-base ri" :class="m.icon"></i>
                                <div>{{ m.label }}</div>
                            </Link>
                        </li>
                    </template>

                    <template v-if="$page.props.auth.isSuperAdmin">
                        <li class="materio-item menu-header mt-5">
                            <span class="materio-item menu-header-text">Plateforme</span>
                        </li>
                        <li class="materio-item menu-item" :class="{ active: isActive('organisations.index') }">
                            <Link class="materio-item menu-link" :href="route('organisations.index')">
                                <i class="materio-item menu-icon icon-base ri ri-building-line"></i>
                                <div>Organisations</div>
                            </Link>
                        </li>
                    </template>

                    <li v-if="organisationSwitcher" class="materio-item px-4 py-3">
                        <label class="materio-item form-label small text-body-secondary mb-1">Organisation</label>
                        <select
                            :value="organisationSwitcher.current?.id"
                            @change="switchOrganisation"
                            class="materio-item form-select form-select-sm"
                        >
                            <option v-for="org in organisationSwitcher.options" :key="org.id" :value="org.id">
                                {{ org.name }} ({{ org.code }})
                            </option>
                        </select>
                    </li>
                </ul>
            </aside>
            <!-- / Sidebar -->

            <div class="materio-item layout-page">
                <!-- Topbar -->
                <nav class="materio-item layout-navbar navbar navbar-expand-xl align-items-center" id="layout-navbar">
                    <div class="materio-item layout-menu-toggle navbar-nav align-items-xl-center me-4 me-xl-0 d-xl-none">
                        <a class="materio-item nav-item nav-link px-0 me-xl-6" href="javascript:void(0)" @click="toggleSidebar">
                            <i class="materio-item icon-base ri ri-menu-line icon-md"></i>
                        </a>
                    </div>

                    <div class="materio-item navbar-nav-right d-flex align-items-center" id="navbar-collapse">
                        <div class="materio-item navbar-nav align-items-center flex-grow-1">
                            <GlobalSearch />
                        </div>

                        <ul class="materio-item navbar-nav flex-row align-items-center ms-auto">
                            <NotificationBell />

                            <li class="materio-item nav-item navbar-dropdown dropdown-user dropdown ms-2">
                                <a class="materio-item nav-link dropdown-toggle hide-arrow p-0" href="javascript:void(0);" data-bs-toggle="dropdown">
                                    <span class="materio-item avatar avatar-online">
                                        <span class="materio-item avatar-initial rounded-circle bg-label-primary">{{ initials($page.props.auth.user.name) }}</span>
                                    </span>
                                </a>
                                <ul class="materio-item dropdown-menu dropdown-menu-end">
                                    <li class="materio-item">
                                        <div class="materio-item dropdown-item">
                                            <div class="materio-item d-flex">
                                                <div class="materio-item flex-shrink-0 me-3">
                                                    <span class="materio-item avatar avatar-online">
                                                        <span class="materio-item avatar-initial rounded-circle bg-label-primary">{{ initials($page.props.auth.user.name) }}</span>
                                                    </span>
                                                </div>
                                                <div class="materio-item flex-grow-1">
                                                    <h6 class="materio-item mb-0">{{ $page.props.auth.user.name }}</h6>
                                                    <small class="materio-item text-body-secondary">{{ $page.props.auth.role ?? '—' }}</small>
                                                </div>
                                            </div>
                                        </div>
                                    </li>
                                    <li><div class="materio-item dropdown-divider"></div></li>
                                    <li>
                                        <Link class="materio-item dropdown-item" :href="route('profile.edit')">
                                            <i class="materio-item icon-base ri ri-user-3-line icon-md me-3"></i>
                                            <span>Profil</span>
                                        </Link>
                                    </li>
                                    <li><div class="materio-item dropdown-divider"></div></li>
                                    <li>
                                        <div class="materio-item d-grid px-4 pt-2 pb-1">
                                            <Link class="materio-item btn btn-danger d-flex" :href="route('logout')" method="post" as="button">
                                                <small class="materio-item align-middle">Déconnexion</small>
                                                <i class="materio-item icon-base ri ri-logout-box-r-line ms-2 icon-xs"></i>
                                            </Link>
                                        </div>
                                    </li>
                                </ul>
                            </li>
                        </ul>
                    </div>
                </nav>
                <!-- / Topbar -->

                <div class="materio-item content-wrapper">
                    <div class="materio-item container-xxl flex-grow-1 container-p-y">
                        <!-- Pas de d-flex ici : le contenu de chaque page gère déjà son propre
                             flex/justify-between interne (titre à gauche, boutons à droite).
                             Empiler deux flex l'un dans l'autre laissait ce conteneur-ci se
                             réduire à la largeur de son unique enfant (flex-item par défaut ne
                             s'étire pas), donc le justify-between interne n'avait plus d'espace
                             où répartir titre et boutons — ils se retrouvaient collés. En bloc
                             simple, l'enfant prend toute la largeur et son propre flex marche. -->
                        <div v-if="$slots.header" class="materio-item mb-4">
                            <slot name="header" />
                        </div>

                        <slot />
                    </div>

                    <footer class="materio-item content-footer footer bg-footer-theme">
                        <div class="materio-item container-xxl d-flex flex-wrap justify-content-between py-3 flex-md-row flex-column">
                            <div class="materio-item text-body-secondary mb-2 mb-md-0">
                                Maintenance Équipement Industriel &copy; {{ year }}
                            </div>
                        </div>
                    </footer>
                </div>
            </div>
        </div>

        <div class="materio-item layout-overlay layout-menu-toggle" @click="toggleSidebar"></div>
        <div class="materio-item drag-target"></div>
    </div>
</template>
