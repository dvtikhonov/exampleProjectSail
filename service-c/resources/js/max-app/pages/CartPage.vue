<script setup>
import { computed } from 'vue';

const props = defineProps({
    cart: {
        type: Object,
        default: null,
    },
    loading: {
        type: Boolean,
        default: false,
    },
    error: {
        type: String,
        default: '',
    },
    submitting: {
        type: Boolean,
        default: false,
    },
    updatingItemId: {
        type: Number,
        default: null,
    },
});

const emit = defineEmits(['update-quantity', 'remove-item', 'submit-order', 'go-to-restaurants']);

const isEmpty = computed(() => !props.cart || props.cart.items.length === 0);
</script>

<template>
    <div class="flex min-h-dvh flex-col pb-28">
        <header class="sticky top-0 z-10 border-b border-gray-200 bg-white px-4 py-3">
            <h1 class="text-lg font-semibold text-gray-900">Корзина</h1>
            <p v-if="cart?.restaurant_name" class="text-sm text-max-muted">{{ cart.restaurant_name }}</p>
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

            <div v-else-if="isEmpty" class="flex flex-col items-center py-16 text-center">
                <div class="mb-4 text-5xl">🛒</div>
                <p class="text-base font-medium text-gray-900">Корзина пуста</p>
                <p class="mt-1 text-sm text-max-muted">Добавьте блюда из меню ресторана</p>
                <button
                    type="button"
                    class="mt-6 rounded-2xl bg-max-primary px-6 py-3 text-sm font-medium text-white transition hover:bg-max-primary-hover"
                    @click="emit('go-to-restaurants')"
                >
                    К ресторанам
                </button>
            </div>

            <ul v-else class="space-y-3">
                <li
                    v-for="item in cart.items"
                    :key="item.id"
                    class="rounded-2xl border border-gray-100 bg-white p-4 shadow-sm"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0 flex-1">
                            <p class="font-medium text-gray-900">{{ item.dish_name }}</p>
                            <p class="mt-0.5 text-sm text-max-muted">{{ item.unit_price }} ₽ × {{ item.quantity }}</p>
                            <p class="mt-1 text-sm font-semibold text-gray-900">{{ item.line_total }} ₽</p>
                        </div>
                        <button
                            type="button"
                            class="text-sm text-red-500 transition hover:text-red-700"
                            :disabled="updatingItemId === item.id"
                            @click="emit('remove-item', item)"
                        >
                            Удалить
                        </button>
                    </div>
                    <div class="mt-3 flex items-center gap-3">
                        <button
                            type="button"
                            class="flex h-9 w-9 items-center justify-center rounded-full border border-gray-200 bg-gray-50 text-lg font-medium transition hover:bg-gray-100 disabled:opacity-40"
                            :disabled="item.quantity <= 1 || updatingItemId === item.id"
                            @click="emit('update-quantity', item, item.quantity - 1)"
                        >
                            −
                        </button>
                        <span class="min-w-6 text-center font-medium">{{ item.quantity }}</span>
                        <button
                            type="button"
                            class="flex h-9 w-9 items-center justify-center rounded-full border border-gray-200 bg-gray-50 text-lg font-medium transition hover:bg-gray-100 disabled:opacity-40"
                            :disabled="item.quantity >= 99 || updatingItemId === item.id"
                            @click="emit('update-quantity', item, item.quantity + 1)"
                        >
                            +
                        </button>
                    </div>
                </li>
            </ul>
        </main>

        <div
            v-if="!isEmpty && !loading"
            class="fixed inset-x-0 bottom-0 z-20 border-t border-gray-200 bg-white px-4 py-3 safe-area-bottom"
        >
            <div class="mb-3 flex items-center justify-between text-base">
                <span class="text-max-muted">Итого</span>
                <span class="text-xl font-bold text-gray-900">{{ cart.total }} ₽</span>
            </div>
            <button
                type="button"
                class="flex w-full items-center justify-center rounded-2xl bg-max-primary px-4 py-3.5 font-medium text-white transition hover:bg-max-primary-hover disabled:cursor-not-allowed disabled:opacity-60"
                :disabled="submitting"
                @click="emit('submit-order')"
            >
                <span v-if="submitting" class="mr-2 h-4 w-4 animate-spin rounded-full border-2 border-white border-t-transparent" />
                Оформить заявку
            </button>
        </div>
    </div>
</template>
