<script setup>
/**
 * Карточка заказа клиента: снимок состава, адрес, итог и чат с оператором.
 */
import DishImage from '../components/DishImage.vue';
import OrderChatPanel from '../components/OrderChatPanel.vue';
import OrderStatusBadge from '../components/OrderStatusBadge.vue';

defineProps({
    order: {
        type: Object,
        required: true,
    },
    loading: {
        type: Boolean,
        default: false,
    },
    error: {
        type: String,
        default: '',
    },
});

const emit = defineEmits(['back', 'messages-read']);
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
                    <div class="flex flex-wrap items-center gap-2">
                        <h1 class="truncate text-lg font-semibold text-gray-900">Заказ №{{ order.id }}</h1>
                        <OrderStatusBadge :order="order" size="md" />
                    </div>
                    <p class="truncate text-sm text-max-muted">{{ order.restaurant_name }}</p>
                </div>
            </div>
        </header>

        <main class="flex min-h-0 flex-1 flex-col gap-4 overflow-hidden px-4 py-4">
            <div v-if="loading" class="flex items-center justify-center py-16">
                <div class="h-8 w-8 animate-spin rounded-full border-4 border-max-primary border-t-transparent" />
            </div>

            <div
                v-else-if="error"
                class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"
            >
                {{ error }}
            </div>

            <template v-else>
                <div class="shrink-0 rounded-2xl border border-gray-100 bg-white p-4 shadow-sm">
                    <p class="text-xs font-medium uppercase tracking-wide text-max-muted">Адрес доставки</p>
                    <p class="mt-1 text-sm text-gray-900">{{ order.delivery_address || '—' }}</p>

                    <ul class="mt-3 space-y-2 border-t border-gray-100 pt-3">
                        <li
                            v-for="(item, index) in order.items_snapshot"
                            :key="index"
                            class="flex items-center gap-3 text-sm"
                        >
                            <DishImage :image-url="item.image_url" :alt="item.dish_name" size="sm" />
                            <span class="min-w-0 flex-1 text-gray-700">{{ item.dish_name }} × {{ item.quantity }}</span>
                            <span class="shrink-0 font-medium text-gray-900">{{ item.line_total }} ₽</span>
                        </li>
                    </ul>

                    <div class="mt-3 border-t border-gray-100 pt-3 text-sm">
                        <template v-if="order.delivery_applicable">
                            <div class="space-y-1.5">
                                <div class="flex items-center justify-between">
                                    <span class="text-max-muted">Сумма блюд</span>
                                    <span class="font-medium text-gray-900">{{ order.items_total }} ₽</span>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span class="text-max-muted">Доставка</span>
                                    <span class="font-medium text-gray-900">{{ order.delivery_cost }} ₽</span>
                                </div>
                                <div class="flex items-center justify-between border-t border-gray-100 pt-2">
                                    <span class="font-medium text-gray-900">Итого</span>
                                    <span class="text-lg font-bold text-gray-900">{{ order.total }} ₽</span>
                                </div>
                            </div>
                        </template>
                        <div v-else class="flex items-center justify-between">
                            <span class="font-medium text-gray-900">Итого</span>
                            <span class="text-lg font-bold text-gray-900">{{ order.total }} ₽</span>
                        </div>
                    </div>
                </div>

                <OrderChatPanel
                    :order-id="order.id"
                    perspective="customer"
                    @messages-read="emit('messages-read')"
                />
            </template>
        </main>
    </div>
</template>
