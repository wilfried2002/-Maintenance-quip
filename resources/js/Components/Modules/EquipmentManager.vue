<script setup>
import { ref, computed } from 'vue';
import { useForm, Link } from '@inertiajs/vue3';
import { themes } from '@/moduleTheme';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import DataTable from '@/Components/DataTable.vue';

const props = defineProps({
    theme: { type: String, required: true },
    items: { type: [Array, Object], required: true },
    fields: { type: Array, required: true },
    storeUrl: { type: String, required: true },
    updateUrlBase: { type: String, required: true },
    destroyUrlBase: { type: String, required: true },
    showUrlBase: { type: String, required: true },
    itemLabel: { type: Function, required: true },
    // Nom du prop de page rafraîchi par la pagination serveur (only:)
    rowsKey: { type: String, default: 'equipements' },
    showFormBlock: { type: Boolean, default: true },
    showTable: { type: Boolean, default: true },
});

const t = themes[props.theme] ?? themes.slate;

// Liste paginée côté serveur (paginator Laravel) — compat tableau conservée.
const itemsRows = computed(() =>
    Array.isArray(props.items) ? props.items : (props.items?.data ?? []));
const itemsPaginees = computed(() =>
    Array.isArray(props.items) ? null : props.items);

const showForm = ref(false);
const editingId = ref(null);
const existingPhotoUrl = ref(null);

function emptyValues() {
    const values = { photo: null };
    props.fields.forEach((field) => {
        values[field.key] = field.default ?? '';
    });
    return values;
}

const form = useForm(emptyValues());

const photoPreview = computed(() => {
    if (form.photo instanceof File) {
        return URL.createObjectURL(form.photo);
    }
    return existingPhotoUrl.value;
});

function openCreate() {
    editingId.value = null;
    existingPhotoUrl.value = null;
    form.defaults(emptyValues());
    form.reset();
    showForm.value = true;
}

function openEdit(item) {
    editingId.value = item.id;
    existingPhotoUrl.value = item.photo_url ?? null;
    const values = { photo: null };
    props.fields.forEach((field) => {
        values[field.key] = item[field.key] ?? '';
    });
    form.defaults(values);
    form.reset();
    showForm.value = true;
}

function cancel() {
    showForm.value = false;
    editingId.value = null;
    existingPhotoUrl.value = null;
}

function onPhotoChange(event) {
    form.photo = event.target.files[0] ?? null;
}

function submit() {
    const url = editingId.value ? `${props.updateUrlBase}/${editingId.value}` : props.storeUrl;

    form.transform((data) => (editingId.value ? { ...data, _method: 'put' } : data));

    form.post(url, {
        forceFormData: true,
        onSuccess: () => {
            showForm.value = false;
            editingId.value = null;
        },
    });
}

function destroy(item) {
    if (confirm(`Supprimer « ${props.itemLabel(item)} » ?`)) {
        form.delete(`${props.destroyUrlBase}/${item.id}`, { preserveScroll: true });
    }
}

// Type de module dans les URLs de rapports (/rapports/fiche/{type}/{id}…).
const rapportType = props.storeUrl.split('/')[1];

const visibleFields = computed(() => props.fields.filter((f) => f.column !== false));
const firstFieldKey = computed(() => props.fields[0]?.key);
const columns = computed(() => [
    { key: 'photo', label: '', sortable: false, class: 'w-14' },
    ...visibleFields.value.map((field) => ({ key: field.key, label: field.label })),
]);
</script>

<template>
    <div>
        <div v-if="showFormBlock" class="mb-4 flex items-center justify-between">
            <button
                type="button"
                @click="showForm ? cancel() : openCreate()"
                class="inline-flex items-center rounded-md px-4 py-2 text-sm font-medium shadow-sm transition focus:outline-none focus:ring-2 focus:ring-offset-2"
                :class="t.button"
            >
                {{ showForm ? 'Annuler' : 'Nouvel enregistrement' }}
            </button>
            <a
                :href="`/rapports/liste/${rapportType}/equipements`"
                target="_blank"
                class="inline-flex items-center gap-1 rounded-md border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800"
            >
                <i class="ri-file-pdf-line"></i> Exporter la liste (PDF)
            </a>
        </div>

        <!-- Formulaire d'enregistrement / édition -->
        <div v-if="showFormBlock && showForm" class="materio-item card shadow mb-4">
            <div class="materio-item card-header py-3">
                <h6 class="materio-item m-0 fw-bold" :class="t.accent">
                    {{ editingId ? 'Modifier l\'enregistrement' : 'Enregistrer un nouvel équipement en stock' }}
                </h6>
            </div>
            <div class="materio-item card-body">
                <form @submit.prevent="submit">
                    <div class="materio-item row align-items-center mb-3">
                        <div class="materio-item col-auto">
                            <img
                                v-if="photoPreview"
                                :src="photoPreview"
                                alt="Photo de l'équipement"
                                class="h-20 w-20 rounded-md object-cover ring-1 ring-black/10"
                            />
                            <div
                                v-else
                                class="flex h-20 w-20 items-center justify-center rounded-md bg-white/60 text-xs text-gray-400 ring-1 ring-black/10 dark:bg-black/20"
                            >
                                Aucune photo
                            </div>
                        </div>
                        <div class="materio-item col">
                            <InputLabel for="photo" value="Photo (optionnel)" />
                            <input
                                id="photo"
                                type="file"
                                accept="image/*"
                                @change="onPhotoChange"
                                class="materio-item form-control-file"
                            />
                            <InputError :message="form.errors.photo" />
                        </div>
                    </div>

                    <div class="materio-item row">
                        <div v-for="field in fields" :key="field.key" class="materio-item form-group" :class="field.wide ? 'col-12' : 'col-md-6'">
                            <InputLabel :for="field.key" :value="field.label" />

                            <select
                                v-if="field.type === 'select'"
                                :id="field.key"
                                v-model="form[field.key]"
                                class="materio-item form-control"
                                :class="{ 'is-invalid': form.errors[field.key] }"
                                :required="field.required"
                            >
                                <option value="" disabled>Sélectionner…</option>
                                <option v-for="opt in field.options" :key="opt.value" :value="opt.value">
                                    {{ opt.label }}
                                </option>
                            </select>

                            <textarea
                                v-else-if="field.type === 'textarea'"
                                :id="field.key"
                                v-model="form[field.key]"
                                rows="3"
                                class="materio-item form-control"
                                :class="{ 'is-invalid': form.errors[field.key] }"
                            ></textarea>

                            <input
                                v-else
                                :id="field.key"
                                v-model="form[field.key]"
                                :type="field.type ?? 'text'"
                                :step="field.type === 'number' ? 'any' : undefined"
                                class="materio-item form-control"
                                :class="{ 'is-invalid': form.errors[field.key] }"
                                :required="field.required"
                            />

                            <InputError :message="form.errors[field.key]" />
                        </div>
                    </div>

                    <div class="flex justify-end gap-2">
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="rounded-md px-4 py-2 text-sm font-medium shadow-sm focus:outline-none focus:ring-2 focus:ring-offset-2 disabled:opacity-50"
                            :class="t.button"
                        >
                            Enregistrer
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Table -->
        <DataTable v-if="showTable"
            :theme="theme"
            :columns="columns"
            :rows="itemsRows"
            :paginated="itemsPaginees"
            :rows-key="rowsKey"
            search-placeholder="Rechercher un enregistrement…"
            empty-text="Aucun enregistrement pour le moment."
        >
            <template #cell-photo="{ row }">
                <Link :href="`${showUrlBase}/${row.id}`">
                    <img
                        v-if="row.photo_url"
                        :src="row.photo_url"
                        alt=""
                        class="h-10 w-10 rounded-md object-cover ring-1 ring-black/10"
                    />
                    <div
                        v-else
                        class="flex h-10 w-10 items-center justify-center rounded-md text-gray-400 ring-1 ring-black/10"
                        :class="t.iconBg"
                    >
                        <svg class="h-5 w-5" :class="t.icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14M4 8h16M4 4h16v16H4V4z" />
                        </svg>
                    </div>
                </Link>
            </template>

            <template v-for="field in visibleFields" :key="field.key" #[`cell-${field.key}`]="{ row }">
                <span
                    v-if="field.type === 'select' && field.key === 'statut'"
                    class="inline-flex rounded-full px-2 py-1 text-xs font-medium"
                    :class="t.badge"
                >
                    {{ field.options.find(o => o.value === row[field.key])?.label ?? row[field.key] }}
                </span>
                <Link v-else-if="field.key === firstFieldKey" :href="`${showUrlBase}/${row.id}`" class="font-medium hover:underline" :class="t.accent">
                    {{ row[field.key] ?? '—' }}
                </Link>
                <span v-else>{{ row[field.key] ?? '—' }}</span>
            </template>

            <template #actions="{ row }">
                <button type="button" class="mr-3 font-medium" :class="t.accent" @click="openEdit(row)">
                    Modifier
                </button>
                <a :href="`/rapports/fiche/${rapportType}/${row.id}`" target="_blank" class="mr-3 font-medium text-gray-600 hover:text-gray-900 dark:text-gray-400" title="Fiche PDF">PDF</a>
                <a :href="`/rapports/etiquette/${rapportType}/${row.id}`" target="_blank" class="mr-3 font-medium text-gray-600 hover:text-gray-900 dark:text-gray-400" title="Étiquette QR (PDF)">Étiquette</a>
                <button type="button" class="font-medium text-red-600 hover:text-red-800" @click="destroy(row)">
                    Supprimer
                </button>
            </template>
        </DataTable>
    </div>
</template>
