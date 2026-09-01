<script setup>
import { computed, ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';

const props = defineProps({
    theme: { type: String, default: 'slate' }, // conservé pour compat, plus utilisé
    title: { type: String, default: null },
    columns: { type: Array, required: true }, // [{ key, label, sortable, align, class }]
    rows: { type: Array, required: true }, // mode client : toutes les lignes ; mode serveur : la page courante
    // Mode SERVEUR : passer le paginator Laravel tel quel ({data, current_page,
    // last_page, per_page, total}). Recherche, tri, page et « par page »
    // déclenchent alors des rechargements partiels Inertia (only: rowsKey) au
    // lieu de tout filtrer en mémoire — indispensable dès quelques centaines de
    // lignes. Laisser à null pour le comportement client historique.
    paginated: { type: Object, default: null },
    rowsKey: { type: String, default: 'rows' }, // prop de page à rafraîchir (only:)
    filters: { type: Object, default: () => ({}) }, // paramètres de filtre additionnels
    rowKey: { type: String, default: 'id' },
    searchable: { type: Boolean, default: true },
    searchPlaceholder: { type: String, default: 'Rechercher…' },
    initialSearch: { type: String, default: '' },
    pageSize: { type: Number, default: 10 },
    emptyText: { type: String, default: 'Aucune donnée.' },
    expandable: { type: Boolean, default: false },
});

const serverMode = computed(() => Boolean(props.paginated));

const search = ref(props.initialSearch);
const sortKey = ref(null);
const sortDir = ref('asc'); // 'asc' | 'desc'
const page = ref(1);
const perPage = ref(serverMode.value ? (props.paginated.per_page ?? 15) : props.pageSize);
const expandedKeys = ref(new Set());

// ─── Mode serveur : rechargement partiel Inertia ─────────────────────────────
let debounceTimer = null;

function fetchServeur(pageCiblee) {
    const params = { ...props.filters, page: pageCiblee, per_page: perPage.value };

    if (search.value.trim()) {
        params.q = search.value.trim();
    }

    if (sortKey.value) {
        params.sort = sortKey.value;
        params.dir = sortDir.value;
    }

    router.get(window.location.pathname, params, {
        only: [props.rowsKey],
        preserveState: true,
        preserveScroll: true,
        // replace : ne pas empiler une entrée d'historique par frappe/clic.
        replace: true,
    });
}

// Le paginator arrive/recycle à chaque réponse : garder la page affichée synchrone
// avec le serveur (et le per_page si l'utilisateur en change ailleurs).
watch(
    () => props.paginated,
    (paginator) => {
        if (paginator?.current_page) {
            page.value = paginator.current_page;
        }
    },
    { immediate: true },
);

// Recherche : en serveur, debouncée puis requête au serveur ; en client, simple reset.
watch(search, () => {
    if (serverMode.value) {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(() => fetchServeur(1), 350);
        return;
    }

    page.value = 1;
});

watch(perPage, () => {
    if (serverMode.value) {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(() => fetchServeur(1), 150);
        return;
    }

    page.value = 1;
});

// Nouvelles données (création/suppression) : repartir de la page 1 en mode client.
watch(() => props.rows, () => {
    if (!serverMode.value) {
        page.value = 1;
    }
});

function goTo(pageCiblee) {
    if (serverMode.value) {
        fetchServeur(pageCiblee);
        return;
    }

    page.value = pageCiblee;
}

// ─── Tri ─────────────────────────────────────────────────────────────────────
function toggleSort(col) {
    if (col.sortable === false) {
        return;
    }

    if (sortKey.value !== col.key) {
        sortKey.value = col.key;
        sortDir.value = 'asc';
    } else if (sortDir.value === 'asc') {
        sortDir.value = 'desc';
    } else {
        sortKey.value = null;
        sortDir.value = 'asc';
    }

    if (serverMode.value) {
        fetchServeur(1);
    } else {
        page.value = 1;
    }
}

// ─── Filtrage/tri/pagination CLIENT (mode historique) ────────────────────────
function rawValue(row, key) {
    return key.split('.').reduce((acc, part) => (acc == null ? acc : acc[part]), row);
}

const filteredRows = computed(() => {
    if (serverMode.value || !props.searchable || !search.value.trim()) {
        return props.rows;
    }

    const needle = search.value.trim().toLowerCase();
    return props.rows.filter((row) =>
        props.columns.some((col) => {
            const value = rawValue(row, col.key);
            if (value === null || value === undefined) return false;
            return String(value).toLowerCase().includes(needle);
        })
    );
});

const sortedRows = computed(() => {
    if (serverMode.value || !sortKey.value) return filteredRows.value;

    const key = sortKey.value;
    const dir = sortDir.value === 'asc' ? 1 : -1;
    return [...filteredRows.value].sort((a, b) => {
        const va = rawValue(a, key);
        const vb = rawValue(b, key);
        if (va === null || va === undefined) return 1;
        if (vb === null || vb === undefined) return -1;
        if (typeof va === 'number' && typeof vb === 'number') return (va - vb) * dir;
        return String(va).localeCompare(String(vb), 'fr', { numeric: true }) * dir;
    });
});

const totalRows = computed(() => (serverMode.value ? (props.paginated?.total ?? 0) : sortedRows.value.length));

const totalPages = computed(() =>
    serverMode.value
        ? Math.max(1, props.paginated?.last_page ?? 1)
        : Math.max(1, Math.ceil(sortedRows.value.length / perPage.value))
);

const paginatedRows = computed(() => {
    if (serverMode.value) {
        return props.rows;
    }

    const start = (page.value - 1) * perPage.value;
    return sortedRows.value.slice(start, start + perPage.value);
});

const rangeStart = computed(() => (totalRows.value === 0 ? 0 : serverMode.value
    ? (page.value - 1) * (props.paginated?.per_page ?? perPage.value) + 1
    : (page.value - 1) * perPage.value + 1));

const rangeEnd = computed(() => (serverMode.value
    ? Math.min(page.value * (props.paginated?.per_page ?? perPage.value), totalRows.value)
    : Math.min(page.value * perPage.value, sortedRows.value.length)));

// En mode serveur, « Tout » n'a pas de sens (plafonné à 100 par le contrôleur).
const perPageOptions = computed(() => (serverMode.value
    ? [10, 25, 50, 100]
    : [10, 25, 50, sortedRows.value.length || 1]));

const videParFiltres = computed(() => {
    if (serverMode.value) {
        return (props.paginated?.total ?? 0) > 0;
    }

    return sortedRows.value.length === 0 && props.rows.length > 0;
});

function isExpanded(row) {
    return expandedKeys.value.has(row[props.rowKey]);
}

function toggleExpand(row) {
    const key = row[props.rowKey];
    const next = new Set(expandedKeys.value);
    if (next.has(key)) {
        next.delete(key);
    } else {
        next.add(key);
    }
    expandedKeys.value = next;
}
</script>

<template>
    <div class="materio-item card shadow mb-4">
        <div class="materio-item card-header py-3 d-flex flex-wrap align-items-center justify-content-between gap-2">
            <h6 v-if="title" class="materio-item m-0 fw-bold" :class="`materio-item text-primary`">{{ title }}</h6>
            <span v-else class="materio-item m-0 fw-bold text-primary small text-uppercase">
                {{ totalRows }} résultat{{ totalRows > 1 ? 's' : '' }}
            </span>
            <div v-if="searchable" class="materio-item input-group input-group-sm" style="max-width: 260px">
                <span class="materio-item input-group-text"><i class="materio-item icon-base ri ri-search-line icon-14px"></i></span>
                <input
                    v-model="search"
                    type="text"
                    :placeholder="searchPlaceholder"
                    class="materio-item form-control"
                />
            </div>
        </div>

        <div class="materio-item card-body">
            <div class="materio-item table-responsive">
                <table class="materio-item table table-bordered table-hover" width="100%">
                    <thead class="materio-item table-light">
                        <tr>
                            <th v-if="expandable" class="materio-item" style="width: 2.25rem"></th>
                            <th
                                v-for="col in columns"
                                :key="col.key"
                                class="materio-item"
                                :class="[col.align === 'right' ? 'materio-item text-end' : '', col.sortable !== false ? 'materio-item text-nowrap' : '', col.class]"
                                style="cursor: pointer; user-select: none"
                                @click="toggleSort(col)"
                            >
                                {{ col.label }}
                                <i
                                    v-if="col.sortable !== false"
                                    class="materio-item icon-base ri icon-14px"
                                    :class="sortKey === col.key ? (sortDir === 'desc' ? 'ri-arrow-down-s-line' : 'ri-arrow-up-s-line') : 'ri-expand-up-down-line text-body-secondary'"
                                ></i>
                            </th>
                            <th v-if="$slots.actions" class="materio-item"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="paginatedRows.length === 0">
                            <td :colspan="columns.length + (expandable ? 1 : 0) + ($slots.actions ? 1 : 0)" class="materio-item text-center text-body-secondary py-5">
                                {{ videParFiltres ? 'Aucun résultat pour cette recherche.' : emptyText }}
                            </td>
                        </tr>
                        <template v-for="row in paginatedRows" :key="row[rowKey]">
                            <tr>
                                <td v-if="expandable" class="materio-item">
                                    <button type="button" class="materio-item btn btn-link btn-sm p-0" @click="toggleExpand(row)">
                                        <i class="materio-item icon-base ri" :class="isExpanded(row) ? 'ri-arrow-down-s-line' : 'ri-arrow-right-s-line'"></i>
                                    </button>
                                </td>
                                <td
                                    v-for="col in columns"
                                    :key="col.key"
                                    class="materio-item"
                                    :class="col.align === 'right' ? 'materio-item text-end' : ''"
                                >
                                    <slot :name="`cell-${col.key}`" :row="row" :is-expanded="isExpanded(row)" :toggle-expand="() => toggleExpand(row)">{{ rawValue(row, col.key) ?? '—' }}</slot>
                                </td>
                                <td v-if="$slots.actions" class="materio-item text-end text-nowrap">
                                    <slot name="actions" :row="row" :is-expanded="isExpanded(row)" :toggle-expand="() => toggleExpand(row)" />
                                </td>
                            </tr>
                            <tr v-if="expandable && isExpanded(row)">
                                <td :colspan="columns.length + 1 + ($slots.actions ? 1 : 0)" class="materio-item bg-light">
                                    <slot name="expanded" :row="row" :toggle-expand="() => toggleExpand(row)" />
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>

            <div v-if="totalRows > 0" class="materio-item d-flex flex-wrap align-items-center justify-content-between gap-2 mt-2">
                <div class="materio-item d-flex align-items-center gap-2 small text-body-secondary">
                    <span>Par page</span>
                    <select v-model.number="perPage" class="materio-item form-select form-select-sm" style="width: auto">
                        <option v-for="option in perPageOptions" :key="option" :value="option">{{ serverMode ? option : (option === (sortedRows.length || 1) ? 'Tout' : option) }}</option>
                    </select>
                </div>
                <div class="materio-item d-flex align-items-center gap-2 small text-body-secondary">
                    <span>{{ rangeStart }}–{{ rangeEnd }} sur {{ totalRows }}</span>
                    <div class="materio-item btn-group btn-group-sm">
                        <button type="button" class="materio-item btn btn-outline-secondary" :disabled="page <= 1" @click="goTo(page - 1)">
                            <i class="materio-item icon-base ri ri-arrow-left-s-line"></i>
                        </button>
                        <button type="button" class="materio-item btn btn-outline-secondary disabled" disabled>{{ page }} / {{ totalPages }}</button>
                        <button type="button" class="materio-item btn btn-outline-secondary" :disabled="page >= totalPages" @click="goTo(page + 1)">
                            <i class="materio-item icon-base ri ri-arrow-right-s-line"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
