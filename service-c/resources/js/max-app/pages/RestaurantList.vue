<script setup>
/**
 * Стартовый экран клиента: список ресторанов.
 * Шапка содержит переход в «Мои заказы» и корзину с бейджем количества.
 */
import MyOrdersButton from '../components/MyOrdersButton.vue';

defineProps({
    restaurants: {
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
    cartItemCount: {
        type: Number,
        default: 0,
    },
    ordersUnreadCount: {
        type: Number,
        default: 0,
    },
});

const emit = defineEmits(['select-restaurant', 'open-cart', 'open-orders']);
</script>

<template>
    <div class="flex min-h-dvh flex-col">
        <header class="sticky top-0 z-10 border-b border-gray-200 bg-white px-4 py-3 safe-area-top">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-lg font-semibold text-gray-900">Рестораны</h1>
                    <p class="text-sm text-max-muted">Выберите, где заказать</p>
                </div>
                <div class="flex items-center gap-2">
                    <MyOrdersButton
                        :unread-count="ordersUnreadCount"
                        @click="emit('open-orders')"
                    />
                    <button
                        type="button"
                        class="relative flex h-10 w-10 items-center justify-center rounded-full bg-max-primary text-white transition hover:bg-max-primary-hover"
                        aria-label="Корзина"
                        @click="emit('open-cart')"
                    >
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" />
                    </svg>
                    <span
                        v-if="cartItemCount > 0"
                        class="absolute -right-1 -top-1 flex h-5 min-w-5 items-center justify-center rounded-full bg-red-500 px-1 text-xs font-bold text-white"
                    >
                        {{ cartItemCount }}
                    </span>
                    </button>
                </div>
            </div>
        </header>

        <main class="flex-1 px-4 py-4">
            <div v-if="loading" class="flex items-center justify-center py-16">
                <div class="h-8 w-8 animate-spin rounded-full border-4 border-max-primary border-t-transparent" />
            </div>

            <div
                v-else-if="error"
                class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"
            >
                {{ error }}
            </div>

            <div v-else-if="restaurants.length === 0" class="py-16 text-center text-sm text-max-muted">
                Нет доступных ресторанов
            </div>

            <ul v-else class="space-y-3">
                <li v-for="restaurant in restaurants" :key="restaurant.id">
                    <button
                        type="button"
                        class="w-full rounded-2xl border border-gray-100 bg-white p-4 text-left shadow-sm transition active:scale-[0.98] hover:border-max-primary/30 hover:shadow-md"
                        @click="emit('select-restaurant', restaurant)"
                    >
                        <div class="flex items-start gap-3">
                            <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-blue-50 text-xl">
                                🍽️
                            </div>
                            <div class="min-w-0 flex-1">
                                <h2 class="truncate font-semibold text-gray-900">{{ restaurant.name }}</h2>
                                <p class="mt-1 text-sm text-max-muted">{{ restaurant.address }}</p>
                            </div>
                            <svg class="mt-1 h-5 w-5 shrink-0 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                            </svg>
                        </div>
                    </button>
                </li>
            </ul>
        </main>
    </div>
</template>
