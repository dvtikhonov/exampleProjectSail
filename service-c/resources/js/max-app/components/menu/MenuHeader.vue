<script setup>
/**
 * Шапка меню: адрес доставки, название ресторана, кнопки корзины и заказов.
 * Клик по блоку адреса (pin + текст) — редактирование адреса;
 * отдельная кнопка «Корзина» — переход в корзину.
 */
import { nextTick, ref } from 'vue';
import DeliveryAddressInput from '../DeliveryAddressInput.vue';
import MyOrdersButton from '../MyOrdersButton.vue';

defineProps({
    deliveryAddress: {
        type: String,
        default: '',
    },
    restaurantName: {
        type: String,
        default: '',
    },
    ordersUnreadCount: {
        type: Number,
        default: 0,
    },
    savingAddress: {
        type: Boolean,
        default: false,
    },
    /** Режим ручного заказа: скрывает кнопку «Заказы» */
    manualOrderMode: {
        type: Boolean,
        default: false,
    },
    customerLabel: {
        type: String,
        default: '',
    },
    /** Показывать кнопку «Назад» (ручной заказ) */
    showBackButton: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits([
    'open-cart',
    'open-orders',
    'go-back',
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
    <header>
        <div class="bg-max-surface px-4 pb-3 pt-3">
            <div class="flex items-start justify-between gap-3">
                <div class="flex min-w-0 flex-1 items-start gap-2">
                    <button
                        v-if="showBackButton"
                        type="button"
                        class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-white text-max-primary transition hover:bg-white/80"
                        aria-label="Назад"
                        @click="$emit('go-back')"
                    >
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
                        </svg>
                    </button>
                    <button
                        type="button"
                        class="flex min-w-0 flex-1 items-start gap-2 text-left"
                        aria-label="Редактировать адрес доставки"
                        @click="startAddressEdit"
                    >
                        <span
                            class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-white text-max-primary"
                            aria-hidden="true"
                        >
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="M12 21s7-4.5 7-10a7 7 0 1 0-14 0c0 5.5 7 10 7 10Z"
                                />
                                <circle cx="12" cy="11" r="2.5" />
                            </svg>
                        </span>
                        <span class="min-w-0 flex-1">
                            <p
                                class="truncate text-sm font-medium text-max-text"
                                :class="!deliveryAddress && 'text-max-muted'"
                            >
                                {{ deliveryAddress || addressPlaceholder }}
                            </p>
                            <p v-if="restaurantName" class="truncate text-xs text-max-muted">
                                {{ restaurantName }}
                            </p>
                            <p
                                v-if="manualOrderMode && customerLabel"
                                class="truncate text-xs text-max-muted"
                            >
                                Заказ для потребителя: {{ customerLabel }}
                            </p>
                        </span>
                    </button>
                </div>
                <div class="flex shrink-0 items-center gap-2">
                    <button
                        type="button"
                        class="rounded-full bg-white px-3 py-1.5 text-sm font-medium text-max-primary transition hover:bg-white/80"
                        aria-label="Корзина"
                        @click="$emit('open-cart')"
                    >
                        Корзина
                    </button>
                    <MyOrdersButton
                        v-if="!manualOrderMode"
                        label="Заказы"
                        :unread-count="ordersUnreadCount"
                        button-class="rounded-full bg-white px-3 py-1.5 text-sm font-medium text-max-primary transition hover:bg-white/80"
                        @click="$emit('open-orders')"
                    />
                </div>
            </div>
            <div v-if="isEditingAddress" class="mt-3">
                <DeliveryAddressInput
                    ref="addressInputRef"
                    :model-value="deliveryAddress"
                    :saving-address="savingAddress"
                    :has-address="deliveryAddress.trim().length > 0"
                    input-id="menu-header-delivery-address"
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
