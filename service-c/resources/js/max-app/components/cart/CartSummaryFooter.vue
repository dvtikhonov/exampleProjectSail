<script setup>
/**
 * Fixed footer корзины: итоги и кнопка оформления заявки.
 */
import { computed } from 'vue';
import CartDeliveryHint from './CartDeliveryHint.vue';
import { formatIsoDateRu } from '../../utils/formatIsoDateRu';

const props = defineProps({
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
    hasAddress: {
        type: Boolean,
        default: false,
    },
    savingAddress: {
        type: Boolean,
        default: false,
    },
    /** Ручные заказы: дата доставки редактируема */
    editableDeliveryDate: {
        type: Boolean,
        default: false,
    },
    /** Локальное значение даты (Y-m-d) при editableDeliveryDate */
    deliveryDate: {
        type: String,
        default: '',
    },
    hasDeliveryDate: {
        type: Boolean,
        default: true,
    },
});

defineEmits(['submit', 'update:deliveryDate']);

const deliveryDateLabel = computed(() => formatIsoDateRu(
    props.editableDeliveryDate ? props.deliveryDate : props.cart?.delivery_date,
));

const showDeliveryDateRow = computed(() => {
    if (props.editableDeliveryDate) {
        return true;
    }

    return Boolean(deliveryDateLabel.value);
});
</script>

<template>
    <div class="max-app-shell-bottom fixed z-20 border-t border-gray-200 bg-white px-4 py-3 safe-area-bottom">
        <div class="mb-2 space-y-1.5 text-sm">
            <template v-if="deliveryApplicable">
                <p class="mb-1 text-base font-medium text-gray-900">Детали</p>
                <div
                    v-if="showDeliveryDateRow"
                    class="flex items-center justify-between gap-3"
                >
                    <label
                        v-if="editableDeliveryDate"
                        class="shrink-0 text-max-muted"
                        for="cart-delivery-date"
                    >
                        Дата доставки
                    </label>
                    <span
                        v-else
                        class="text-max-muted"
                    >Дата доставки</span>
                    <input
                        v-if="editableDeliveryDate"
                        id="cart-delivery-date"
                        type="date"
                        class="min-w-0 max-w-[11rem] rounded-xl border border-gray-200 bg-white px-2.5 py-1.5 text-sm font-medium text-gray-900 outline-none focus:border-max-primary focus:ring-2 focus:ring-max-primary/20"
                        :value="deliveryDate"
                        @input="$emit('update:deliveryDate', $event.target.value)"
                    >
                    <span
                        v-else
                        class="font-medium text-gray-900"
                    >{{ deliveryDateLabel }}</span>
                </div>
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
            <div v-else class="space-y-1.5">
                <div
                    v-if="showDeliveryDateRow"
                    class="flex items-center justify-between gap-3 text-sm"
                >
                    <label
                        v-if="editableDeliveryDate"
                        class="shrink-0 text-max-muted"
                        for="cart-delivery-date"
                    >
                        Дата доставки
                    </label>
                    <span
                        v-else
                        class="text-max-muted"
                    >Дата доставки</span>
                    <input
                        v-if="editableDeliveryDate"
                        id="cart-delivery-date"
                        type="date"
                        class="min-w-0 max-w-[11rem] rounded-xl border border-gray-200 bg-white px-2.5 py-1.5 text-sm font-medium text-gray-900 outline-none focus:border-max-primary focus:ring-2 focus:ring-max-primary/20"
                        :value="deliveryDate"
                        @input="$emit('update:deliveryDate', $event.target.value)"
                    >
                    <span
                        v-else
                        class="font-medium text-gray-900"
                    >{{ deliveryDateLabel }}</span>
                </div>
                <div class="flex items-center justify-between text-base">
                    <span class="font-medium text-gray-900">Итого</span>
                    <span class="text-xl font-bold text-gray-900">{{ cart.total }} ₽</span>
                </div>
            </div>
        </div>
        <p
            v-if="!hasAddress"
            class="mb-2 text-center text-xs text-amber-600"
        >
            Сначала укажите адрес
        </p>
        <p
            v-else-if="editableDeliveryDate && !hasDeliveryDate"
            class="mb-2 text-center text-xs text-amber-600"
        >
            Укажите дату доставки
        </p>
        <p
            v-else-if="hasAddress && !canSubmit && savingAddress"
            class="mb-2 text-center text-xs text-amber-600"
        >
            Сохранение адреса…
        </p>
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
