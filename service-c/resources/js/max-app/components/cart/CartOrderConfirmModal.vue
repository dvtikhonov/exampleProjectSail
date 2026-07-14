<script setup>
/**
 * Модалка подтверждения заявки перед отправкой заказа.
 */
import { getCartGroupTitle } from '../../utils/cartGroups';

defineProps({
    open: {
        type: Boolean,
        default: false,
    },
    cartGroups: {
        type: Array,
        default: () => [],
    },
    deliveryAddress: {
        type: String,
        default: '',
    },
    cart: {
        type: Object,
        default: null,
    },
    deliveryApplicable: {
        type: Boolean,
        default: false,
    },
    submitting: {
        type: Boolean,
        default: false,
    },
});

defineEmits(['close', 'confirm']);
</script>

<template>
    <div
        v-if="open"
        class="fixed inset-0 z-30 flex items-end justify-center bg-black/40 px-4 pb-4 pt-8 safe-area-bottom"
        role="dialog"
        aria-modal="true"
        aria-labelledby="order-confirm-title"
        @click.self="$emit('close')"
    >
        <div class="w-full max-w-lg rounded-2xl bg-white p-4 shadow-xl">
            <h2 id="order-confirm-title" class="text-lg font-semibold text-gray-900">
                Подтвердите заявку
            </h2>
            <p class="mt-1 text-sm text-max-muted">
                Проверьте состав заказа и адрес доставки перед отправкой
            </p>

            <div class="mt-4 max-h-48 overflow-y-auto rounded-xl border border-gray-100 bg-gray-50 p-3">
                <ul class="space-y-2 text-sm">
                    <li
                        v-for="item in cartGroups"
                        :key="item.key"
                        class="flex items-center justify-between gap-3"
                    >
                        <span class="min-w-0 text-gray-700">{{ getCartGroupTitle(item) }} × {{ item.quantity }}</span>
                        <span class="shrink-0 font-medium text-gray-900">{{ item.lineTotal }} ₽</span>
                    </li>
                </ul>
            </div>

            <div class="mt-4 rounded-xl border border-gray-100 bg-gray-50 p-3 text-sm">
                <p class="text-xs font-medium uppercase tracking-wide text-max-muted">Адрес доставки</p>
                <p class="mt-1 text-gray-900">{{ deliveryAddress.trim() }}</p>
            </div>

            <div class="mt-4 space-y-1.5 text-sm">
                <template v-if="deliveryApplicable && cart">
                    <div class="flex items-center justify-between">
                        <span class="text-max-muted">Сумма блюд</span>
                        <span class="font-medium text-gray-900">{{ cart.items_total }} ₽</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-max-muted">Доставка</span>
                        <span class="font-medium text-gray-900">{{ cart.delivery_cost }} ₽</span>
                    </div>
                </template>
                <div class="flex items-center justify-between border-t border-gray-200 pt-2 text-base">
                    <span class="font-medium text-gray-900">Итого</span>
                    <span class="text-lg font-bold text-gray-900">{{ cart?.total }} ₽</span>
                </div>
            </div>

            <div class="mt-5 flex gap-3">
                <button
                    type="button"
                    class="flex-1 rounded-2xl border border-gray-200 bg-white px-4 py-3 font-medium text-gray-700 transition hover:bg-gray-50 disabled:opacity-40"
                    :disabled="submitting"
                    @click="$emit('close')"
                >
                    Отмена
                </button>
                <button
                    type="button"
                    class="flex flex-1 items-center justify-center rounded-2xl bg-max-primary px-4 py-3 font-medium text-white transition hover:bg-max-primary-hover disabled:opacity-60"
                    :disabled="submitting"
                    @click="$emit('confirm')"
                >
                    <span
                        v-if="submitting"
                        class="mr-2 h-4 w-4 animate-spin rounded-full border-2 border-white border-t-transparent"
                    />
                    Подтвердить
                </button>
            </div>
        </div>
    </div>
</template>
