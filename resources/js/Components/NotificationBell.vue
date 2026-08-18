<script setup>
import { computed } from 'vue';
import { usePage, router } from '@inertiajs/vue3';

const page = usePage();

const notifications = computed(() => page.props.notifications ?? { unreadCount: 0, items: [] });

function openItem(item) {
    if (!item.read) {
        router.post(route('notifications.read', item.id), {}, { preserveScroll: true, preserveState: true, only: ['notifications'] });
    }
    if (item.url) {
        router.visit(item.url);
    }
}

function markAllRead() {
    router.post(route('notifications.read-all'), {}, { preserveScroll: true, preserveState: true, only: ['notifications'] });
}

function relativeTime(value) {
    const diffMs = new Date(value).getTime() - Date.now();
    const diffMin = Math.round(diffMs / 60000);
    const rtf = new Intl.RelativeTimeFormat('fr', { numeric: 'auto' });
    if (Math.abs(diffMin) < 60) return rtf.format(diffMin, 'minute');
    const diffH = Math.round(diffMin / 60);
    if (Math.abs(diffH) < 24) return rtf.format(diffH, 'hour');
    return rtf.format(Math.round(diffH / 24), 'day');
}
</script>

<template>
    <li class="materio-item nav-item dropdown">
        <a class="materio-item nav-link dropdown-toggle hide-arrow" href="javascript:void(0);" data-bs-toggle="dropdown" data-bs-auto-close="outside">
            <i class="materio-item icon-base ri ri-notification-3-line icon-md"></i>
            <span v-if="notifications.unreadCount > 0" class="materio-item badge rounded-pill bg-danger badge-notifications">
                {{ notifications.unreadCount > 9 ? '9+' : notifications.unreadCount }}
            </span>
        </a>
        <ul class="materio-item dropdown-menu dropdown-menu-end py-0" style="min-width: 22rem">
            <li class="materio-item dropdown-menu-header border-bottom">
                <div class="materio-item dropdown-header d-flex align-items-center py-3">
                    <h6 class="materio-item mb-0 me-auto">Notifications</h6>
                    <button v-if="notifications.unreadCount > 0" type="button" class="materio-item btn btn-link btn-sm p-0" @click="markAllRead">
                        Tout marquer comme lu
                    </button>
                </div>
            </li>
            <li class="materio-item" style="max-height: 22rem; overflow-y: auto">
                <button
                    v-for="item in notifications.items"
                    :key="item.id"
                    type="button"
                    class="materio-item dropdown-item d-flex w-100"
                    @click="openItem(item)"
                >
                    <div class="materio-item flex-shrink-0 me-3">
                        <span class="materio-item avatar" :class="item.read ? '' : 'avatar-online'">
                            <span class="materio-item avatar-initial rounded-circle bg-label-primary">
                                <i class="materio-item icon-base ri ri-notification-3-line"></i>
                            </span>
                        </span>
                    </div>
                    <div class="materio-item flex-grow-1 text-start">
                        <small class="materio-item text-body-secondary">{{ relativeTime(item.created_at) }}</small>
                        <p class="materio-item mb-0" :class="item.read ? '' : 'fw-medium'">{{ item.title }}</p>
                        <small class="materio-item text-body-secondary text-truncate d-block">{{ item.body }}</small>
                    </div>
                </button>
                <p v-if="notifications.items.length === 0" class="materio-item dropdown-item text-center small text-body-secondary mb-0 py-3">
                    Aucune notification.
                </p>
            </li>
        </ul>
    </li>
</template>
