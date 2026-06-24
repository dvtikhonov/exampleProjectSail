<script setup>
import { onMounted, onUnmounted, ref } from 'vue';

const props = defineProps({
    orders: {
        type: Array,
        default: () => [],
    },
    loading: {
        type: Boolean,
        default: false,
    },
    error: {
        type: String,
        default: '',
    },
    refreshing: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['select-order', 'refresh']);

const pullDistance = ref(0);
const isPulling = ref(false);
let touchStartY = 0;
let scrollContainer = null;

const PULL_THRESHOLD = 72;

/**
 * @param {{ first_name?: string|null, last_name?: string|null, username?: string|null, max_user_id: number }} customer
 */
function formatCustomerName(customer) {
    const parts = [customer.first_name, customer.last_name].filter(Boolean);

    if (parts.length > 0) {
        return parts.join(' ');
    }

    if (customer.username) {
        return `@${customer.username}`;
    }

    return `ID ${customer.max_user_id}`;
}

/**
 * @param {string} iso
 */
function formatDate(iso) {
    try {
        return new Intl.DateTimeFormat('ru-RU', {
            day: 'numeric',
            month: 'short',
            hour: '2-digit',
            minute: '2-digit',
        }).format(new Date(iso));
    } catch {
        return iso;
    }
}

function onTouchStart(event) {
    if (!scrollContainer || scrollContainer.scrollTop > 0 || props.loading || props.refreshing) {
        isPulling.value = false;
        return;
    }

    touchStartY = event.touches[0].clientY;
    isPulling.value = true;
}

function onTouchMove(event) {
    if (!isPulling.value) {
        return;
    }

    const delta = event.touches[0].clientY - touchStartY;

    if (delta <= 0) {
        pullDistance.value = 0;
        return;
    }

    pullDistance.value = Math.min(delta * 0.5, PULL_THRESHOLD * 1.5);
}

function onTouchEnd() {
    if (isPulling.value && pullDistance.value >= PULL_THRESHOLD && !props.refreshing) {
        emit('refresh');
    }

    isPulling.value = false;
    pullDistance.value = 0;
}

onMounted(() => {
    scrollContainer = document.getElementById('admin-order-list-scroll');
});

onUnmounted(() => {
    scrollContainer = null;
});
</script>

<template>
    <div class="flex min-h-0 flex-1 flex-col">
        <div
            class="flex shrink-0 items-center justify-center overflow-hidden text-xs text-max-muted transition-[height]"
            :style="{ height: `${Math.max(pullDistance, refreshing ? 40 : 0)}px` }"
        >
            <span v-if="refreshing">Обновление…</span>
            <span v-else-if="pullDistance >= PULL_THRESHOLD">Отпустите для обновления</span>
            <span v-else-if="pullDistance > 0">Потяните вниз</span>
        </div>

        <div
            id="admin-order-list-scroll"
            class="min-h-0 flex-1 overflow-y-auto px-4 pb-4"
            @touchstart.passive="onTouchStart"
            @touchmove.passive="onTouchMove"
            @touchend="onTouchEnd"
        >
            <div v-if="loading && orders.length === 0" class="flex items-center justify-center py-16">
                <div class="h-8 w-8 animate-spin rounded-full border-4 border-max-primary border-t-transparent" />
            </div>

            <div
                v-else-if="error"
                class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"
            >
                {{ error }}
                <button
                    type="button"
                    class="mt-2 block font-medium text-red-800 underline"
                    @click="emit('refresh')"
                >
                    Повторить
                </button>
            </div>

            <div v-else-if="orders.length === 0" class="py-16 text-center text-sm text-max-muted">
                Нет заказов в очереди
            </div>

            <ul v-else class="space-y-3">
                <li v-for="order in orders" :key="order.id">
                    <button
                        type="button"
                        class="w-full rounded-2xl border border-gray-100 bg-white p-4 text-left shadow-sm transition active:scale-[0.98] hover:border-max-primary/30 hover:shadow-md"
                        @click="emit('select-order', order)"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-2">
                                    <span class="font-semibold text-gray-900">№{{ order.id }}</span>
                                    <span class="text-xs text-max-muted">{{ formatDate(order.created_at) }}</span>
                                </div>
                                <p class="mt-1 truncate text-sm text-gray-700">{{ order.restaurant_name }}</p>
                                <p class="mt-0.5 truncate text-sm text-max-muted">
                                    {{ formatCustomerName(order.customer) }}
                                </p>
                                <p
                                    v-if="order.delivery_address"
                                    class="mt-1 line-clamp-2 text-sm text-gray-600"
                                >
                                    {{ order.delivery_address }}
                                </p>
                            </div>
                            <div class="shrink-0 text-right">
                                <p class="font-semibold text-gray-900">{{ order.total }} ₽</p>
                                <svg
                                    class="ml-auto mt-2 h-5 w-5 text-gray-300"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke="currentColor"
                                    stroke-width="2"
                                >
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                                </svg>
                            </div>
                        </div>
                    </button>
                </li>
            </ul>
        </div>
    </div>
</template>
