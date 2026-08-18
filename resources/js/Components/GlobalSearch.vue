<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
import { Link } from '@inertiajs/vue3';
import axios from 'axios';

const q = ref('');
const groups = ref([]);
const loading = ref(false);
const open = ref(false);
const root = ref(null);

let debounceTimer = null;
let abortController = null;

function onInput() {
    clearTimeout(debounceTimer);
    const value = q.value.trim();

    if (value.length < 2) {
        groups.value = [];
        open.value = false;
        return;
    }

    debounceTimer = setTimeout(() => runSearch(value), 250);
}

async function runSearch(value) {
    abortController?.abort();
    abortController = new AbortController();
    loading.value = true;

    try {
        const { data } = await axios.get(route('search.index'), {
            params: { q: value },
            signal: abortController.signal,
        });
        groups.value = data.results;
        open.value = true;
    } catch (error) {
        if (error.name !== 'CanceledError' && error.code !== 'ERR_CANCELED') {
            throw error;
        }
    } finally {
        loading.value = false;
    }
}

function close() {
    open.value = false;
}

function onClickOutside(event) {
    if (root.value && !root.value.contains(event.target)) {
        close();
    }
}

onMounted(() => document.addEventListener('click', onClickOutside));
onUnmounted(() => document.removeEventListener('click', onClickOutside));
</script>

<template>
    <div ref="root" class="materio-item position-relative nav-item d-flex align-items-center" style="width: 100%; max-width: 24rem">
        <i class="materio-item icon-base ri ri-search-line icon-lg lh-0 me-2 text-body-secondary"></i>
        <input
            v-model="q"
            type="text"
            class="materio-item form-control border-0 shadow-none"
            placeholder="Rechercher…"
            autocomplete="off"
            @input="onInput"
            @focus="groups.length && (open = true)"
        />
        <i v-if="loading" class="materio-item icon-base ri ri-loader-4-line icon-md text-body-secondary" style="animation: spin 0.7s linear infinite"></i>

        <div
            v-if="open"
            class="materio-item card shadow position-absolute"
            style="top: 100%; left: 0; right: 0; z-index: 1050; margin-top: 0.5rem; max-height: 70vh; overflow-y: auto"
        >
            <div v-if="groups.length === 0" class="materio-item card-body small text-body-secondary">
                Aucun résultat pour « {{ q }} ».
            </div>
            <div v-for="group in groups" :key="group.group" class="materio-item">
                <div class="materio-item px-3 pt-2 pb-1 small fw-semibold text-body-secondary text-uppercase">
                    {{ group.label }}
                </div>
                <Link
                    v-for="item in group.items"
                    :key="item.id"
                    :href="item.url"
                    class="materio-item dropdown-item"
                    style="white-space: normal"
                    @click="close"
                >
                    <div class="materio-item">{{ item.title }}</div>
                    <div v-if="item.subtitle" class="materio-item small text-body-secondary">{{ item.subtitle }}</div>
                </Link>
            </div>
        </div>
    </div>
</template>

<style scoped>
@keyframes spin {
    to { transform: rotate(360deg); }
}
</style>
