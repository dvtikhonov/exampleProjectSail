<script setup>
/**
 * Fixed footer корзины: итоги и кнопка оформления заявки.
 */
import CartDeliveryHint from './CartDeliveryHint.vue';

defineProps({
    cart: {
        type: Object,
        required: true,
    },
    deliveryApplicable: {
        type: Boolean,
        default: false,
    },
    canSubmit: {
        type: Boolean,
        default: false,
    },
});

defineEmits(['submit']);
</script>

<template>
    <div class="max-app-shell-bottom fixed z-20 border-t border-gray-200 bg-white px-4 py-3 safe-area-bottom">
        <div class="mb-2 space-y-1.5 text-sm">
            <template v-if="deliveryApplicable">
                <p class="mb-1 text-base font-medium text-gray-900">Детали</p>
                <div class="flex items-center justify-between">
                    <span class="text-max-muted">Сумма блюд</span>
                    <span class="font-medium text-gray-900">{{ cart.items_total }} ₽</span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-max-muted">Доставка</span>
                    <span class="font-medium text-gray-900">{{ cart.delivery_cost }} ₽</span>
                </div>
                <CartDeliveryHint :cart="cart" :delivery-applicable="deliveryApplicable" />
                <div class="flex items-center justify-between border-t border-gray-100 pt-2 text-base">
                    <span class="font-medium text-gray-900">Итого</span>
                    <span class="text-xl font-bold text-gray-900">{{ cart.total }} ₽</span>
                </div>
            </template>
            <div v-else class="flex items-center justify-between text-base">
                <span class="font-medium text-gray-900">Итого</span>
                <span class="text-xl font-bold text-gray-900">{{ cart.total }} ₽</span>
            </div>
        </div>
        <button
            type="button"
            class="flex w-full items-center justify-center rounded-2xl bg-max-primary px-4 py-3.5 font-medium text-white transition hover:bg-max-primary-hover disabled:cursor-not-allowed disabled:opacity-60"
            :disabled="!canSubmit"
            @click="$emit('submit')"
        >
            Оформить заявку на {{ cart.total }} ₽
        </button>
    </div>
</template>
