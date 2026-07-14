<script setup>
/**
 * Шапка корзины: назад, адрес доставки, заказы, очистить.
 * Клик по значку pin — редактирование адреса в шапке (как в MenuHeader).
 * Визуально согласована с MenuHeader (bg-max-surface, скругление снизу).
 */
import { nextTick, ref } from 'vue';
import DeliveryAddressInput from '../DeliveryAddressInput.vue';
import MyOrdersButton from '../MyOrdersButton.vue';

defineProps({
    deliveryAddress: {
        type: String,
        default: '',
    },
    loading: {
        type: Boolean,
        default: false,
    },
    submitting: {
        type: Boolean,
        default: false,
    },
    clearing: {
        type: Boolean,
        default: false,
    },
    savingAddress: {
        type: Boolean,
        default: false,
    },
    isEmpty: {
        type: Boolean,
        default: true,
    },
    ordersUnreadCount: {
        type: Number,
        default: 0,
    },
});

const emit = defineEmits([
    'go-back',
    'open-orders',
    'clear-cart',
    'update:deliveryAddress',
    'delivery-address-input',
    'delivery-address-blur',
    'delivery-address-focus',
]);

const addressPlaceholder = 'Укажите адрес доставки';
const isEditingAddress = ref(false);
const addressInputRef = ref(null);

async function startAddressEdit() {
    isEditingAddress.value = true;
    emit('delivery-address-focus');

    await nextTick();
    addressInputRef.value?.focus();
}

function handleAddressFocus() {
    emit('delivery-address-focus');
}

function handleAddressInput(value) {
    emit('update:deliveryAddress', value);
    emit('delivery-address-input', value);
}

function handleAddressBlur(value) {
    emit('delivery-address-blur', value);
    isEditingAddress.value = false;
}
</script>

<template>
    <header class="sticky top-0 z-10 safe-area-top">
        <div class="bg-max-surface px-4 pb-3 pt-3">
            <div class="flex items-start justify-between gap-3">
                <div class="flex min-w-0 flex-1 items-start gap-2">
                    <button
                        type="button"
                        class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-white text-max-primary transition hover:bg-white/80 disabled:opacity-40"
                        :disabled="loading || submitting"
                        aria-label="Назад"
                        @click="$emit('go-back')"
                    >
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
                        </svg>
                    </button>
                    <div class="flex min-w-0 flex-1 items-center gap-2">
                        <button
                            type="button"
                            class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-white text-max-primary transition hover:bg-white/80 disabled:opacity-40"
                            :disabled="loading || isEmpty"
                            aria-label="Редактировать адрес доставки"
                            @click="startAddressEdit"
                        >
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="M12 21s7-4.5 7-10a7 7 0 1 0-14 0c0 5.5 7 10 7 10Z"
                                />
                                <circle cx="12" cy="11" r="2.5" />
                            </svg>
                        </button>
                        <p
                            class="min-w-0 flex-1 truncate text-sm font-medium text-max-text"
                            :class="!deliveryAddress && 'text-max-muted'"
                        >
                            {{ deliveryAddress || addressPlaceholder }}
                        </p>
                    </div>
                </div>
                <div class="flex shrink-0 items-center gap-2">
                    <MyOrdersButton
                        label="Заказы"
                        :unread-count="ordersUnreadCount"
                        button-class="rounded-full bg-white px-3 py-1.5 text-sm font-medium text-max-primary transition hover:bg-white/80"
                        @click="$emit('open-orders')"
                    />
                    <button
                        v-if="!isEmpty && !loading"
                        type="button"
                        class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-white text-red-500 transition hover:bg-white/80 disabled:opacity-40"
                        :disabled="clearing || submitting || savingAddress"
                        aria-label="Очистить корзину"
                        @click="$emit('clear-cart')"
                    >
                        <svg
                            class="h-4 w-4"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                            stroke-width="2"
                            aria-hidden="true"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"
                            />
                        </svg>
                    </button>
                </div>
            </div>
            <div v-if="isEditingAddress" class="mt-3">
                <DeliveryAddressInput
                    ref="addressInputRef"
                    :model-value="deliveryAddress"
                    :saving-address="savingAddress"
                    :has-address="deliveryAddress.trim().length > 0"
                    input-id="cart-header-delivery-address"
                    :rows="2"
                    :show-hints="true"
                    @focus="handleAddressFocus"
                    @update:model-value="handleAddressInput"
                    @blur="handleAddressBlur"
                />
            </div>
        </div>
        <div class="h-3 rounded-t-[1.5rem] bg-white" aria-hidden="true" />
    </header>
</template>
