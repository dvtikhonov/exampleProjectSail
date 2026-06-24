<script setup>
/**
 * Список заказов клиента с статусом, суммой и счётчиком непрочитанных сообщений.
 */
import OrderStatusBadge from '../components/OrderStatusBadge.vue';

defineProps({
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

const emit = defineEmits(['select-order', 'refresh', 'back']);

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

/**
 * @param {{ last_message_at?: string|null, created_at: string }} order
 */
function formatOrderDate(order) {
    return formatDate(order.last_message_at ?? order.created_at);
}
</script>

<template>
    <div class="flex min-h-dvh flex-col">
        <header class="sticky top-0 z-10 border-b border-gray-200 bg-white px-4 py-3 safe-area-top">
            <div class="flex items-center gap-3">
                <button
                    type="button"
                    class="flex h-9 w-9 items-center justify-center rounded-full text-gray-600 transition hover:bg-gray-100"
                    aria-label="Назад"
                    @click="emit('back')"
                >
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
                    </svg>
                </button>
                <div class="min-w-0 flex-1">
                    <h1 class="text-lg font-semibold text-gray-900">Мои заказы</h1>
                    <p class="text-sm text-max-muted">История и переписка</p>
                </div>
                <button
                    type="button"
                    class="rounded-full px-3 py-1.5 text-sm font-medium text-max-primary transition hover:bg-max-primary/10 disabled:opacity-50"
                    :disabled="loading || refreshing"
                    @click="emit('refresh')"
                >
                    {{ refreshing ? '…' : 'Обновить' }}
                </button>
            </div>
        </header>

        <main class="flex-1 px-4 py-4">
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

            <div v-else-if="orders.length === 0" class="py-16 text-center">
                <div class="mb-4 text-5xl">📋</div>
                <p class="text-base font-medium text-gray-900">Заказов пока нет</p>
                <p class="mt-1 text-sm text-max-muted">Оформите заказ в ресторане — он появится здесь</p>
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
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="font-semibold text-gray-900">№{{ order.id }}</span>
                                    <OrderStatusBadge :order="order" />
                                    <span
                                        v-if="order.unread_count > 0"
                                        class="inline-flex h-5 min-w-5 items-center justify-center rounded-full bg-red-500 px-1.5 text-xs font-bold text-white"
                                        :aria-label="`${order.unread_count} новых сообщений`"
                                    >
                                        {{ order.unread_count }}
                                    </span>
                                </div>
                                <p class="mt-1 truncate text-sm text-gray-700">{{ order.restaurant_name }}</p>
                                <p class="mt-0.5 text-xs text-max-muted">{{ formatOrderDate(order) }}</p>
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
        </main>
    </div>
</template>
