<script setup>
import { computed, ref, watch } from 'vue';

const props = defineProps({
    theme: { type: String, default: 'slate' }, // conservé pour compat, plus utilisé (couleurs SB Admin 2 fixes)
    title: { type: String, default: null },
    columns: { type: Array, required: true }, // [{ key, label, sortable, align, class }]
    rows: { type: Array, required: true },
    rowKey: { type: String, default: 'id' },
    searchable: { type: Boolean, default: true },
    searchPlaceholder: { type: String, default: 'Rechercher…' },
    initialSearch: { type: String, default: '' },
    pageSize: { type: Number, default: 10 },
    emptyText: { type: String, default: 'Aucune donnée.' },
    expandable: { type: Boolean, default: false },
});

const search = ref(props.initialSearch);
const sortKey = ref(null);
const sortDir = ref('asc'); // 'asc' | 'desc'
const page = ref(1);
const perPage = ref(props.pageSize);
const expandedKeys = ref(new Set());

function rawValue(row, key) {
    return key.split('.').reduce((acc, part) => (acc == null ? acc : acc[part]), row);
}

function toggleSort(col) {
    if (col.sortable === false) return;
    if (sortKey.value !== col.key) {
        sortKey.value = col.key;
        sortDir.value = 'asc';
    } else if (sortDir.value === 'asc') {
        sortDir.value = 'desc';
    } else {
        sortKey.value = null;
        sortDir.value = 'asc';
    }
    page.value = 1;
}

const filteredRows = computed(() => {
    if (!props.searchable || !search.value.trim()) {
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
    if (!sortKey.value) return filteredRows.value;
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

const totalPages = computed(() => Math.max(1, Math.ceil(sortedRows.value.length / perPage.value)));

const paginatedRows = computed(() => {
    const start = (page.value - 1) * perPage.value;
    return sortedRows.value.slice(start, start + perPage.value);
});

const rangeStart = computed(() => (sortedRows.value.length === 0 ? 0 : (page.value - 1) * perPage.value + 1));
const rangeEnd = computed(() => Math.min(page.value * perPage.value, sortedRows.value.length));

watch([search, perPage], () => { page.value = 1; });
watch(() => props.rows, () => { page.value = 1; });

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
                {{ sortedRows.length }} résultat{{ sortedRows.length > 1 ? 's' : '' }}
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
                                {{ sortedRows.length === 0 && rows.length > 0 ? 'Aucun résultat pour cette recherche.' : emptyText }}
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

            <div v-if="sortedRows.length > 0" class="materio-item d-flex flex-wrap align-items-center justify-content-between gap-2 mt-2">
                <div class="materio-item d-flex align-items-center gap-2 small text-body-secondary">
                    <span>Par page</span>
                    <select v-model.number="perPage" class="materio-item form-select form-select-sm" style="width: auto">
                        <option :value="10">10</option>
                        <option :value="25">25</option>
                        <option :value="50">50</option>
                        <option :value="sortedRows.length || 1">Tout</option>
                    </select>
                </div>
                <div class="materio-item d-flex align-items-center gap-2 small text-body-secondary">
                    <span>{{ rangeStart }}–{{ rangeEnd }} sur {{ sortedRows.length }}</span>
                    <div class="materio-item btn-group btn-group-sm">
                        <button type="button" class="materio-item btn btn-outline-secondary" :disabled="page <= 1" @click="page--">
                            <i class="materio-item icon-base ri ri-arrow-left-s-line"></i>
                        </button>
                        <button type="button" class="materio-item btn btn-outline-secondary disabled" disabled>{{ page }} / {{ totalPages }}</button>
                        <button type="button" class="materio-item btn btn-outline-secondary" :disabled="page >= totalPages" @click="page++">
                            <i class="materio-item icon-base ri ri-arrow-right-s-line"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
