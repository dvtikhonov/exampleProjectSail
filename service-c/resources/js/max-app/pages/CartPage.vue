<script setup>
/**
 * Экран корзины: позиции, адрес доставки, итоги, подтверждение заявки и очистки.
 *
 * Scope (см. ../components/cart/cartScope.js):
 * - header (в т.ч. адрес доставки), список позиций, fixed footer с итогами, модалки подтверждения
 *
 * Вне scope — не добавлять на этот экран:
 * - upsell «Добавить к заказу?», блок «Акции»
 * - OrderChatPanel (чат — OrderDetailPage после submit)
 * - API /api/food/cart/messages
 *
 * Адрес синхронизируется с сервером через debounce (родитель App.vue).
 * Модалки подтверждения перехватывают кнопку «Назад» через defineExpose.
 *
 * @typedef {import('../api/types.js').CartDto} CartDto
 */
import { computed, ref, watch } from 'vue';
import CartHeader from '../components/cart/CartHeader.vue';
import CartItemList from '../components/cart/CartItemList.vue';
import CartOrderConfirmModal from '../components/cart/CartOrderConfirmModal.vue';
import CartSummaryFooter from '../components/cart/CartSummaryFooter.vue';
import ConfirmDeleteModal from '../components/ConfirmDeleteModal.vue';
import EmptyStateIcon from '../components/EmptyStateIcon.vue';
import { buildCartGroups } from '../utils/cartGroups';

const props = defineProps({
    /** Корзина с API (CartDto), null если пусто */
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
        type: [Number, String],
        default: null,
    },
    savingAddress: {
        type: Boolean,
        default: false,
    },
    clearing: {
        type: Boolean,
        default: false,
    },
    ordersUnreadCount: {
        type: Number,
        default: 0,
    },
    isSingleRestaurantMode: {
        type: Boolean,
        default: false,
    },
    manualOrderMode: {
        type: Boolean,
        default: false,
    },
    /**
     * Дата доставки из черновика после сканирования (Y-m-d).
     * В manual-режиме подставляется в поле и фиксируется, чтобы GET корзины
     * с preview MenuAvailabilityDateResolver её не перезаписал.
     */
    preferredDeliveryDate: {
        type: String,
        default: null,
    },
});

const emit = defineEmits([
    'update-quantity',
    'remove-item',
    'clear-cart',
    'submit-order',
    'go-back',
    'go-to-restaurants',
    'delivery-address-input',
    'delivery-address-blur',
    'delivery-address-focus',
    'open-orders',
]);

const localAddress = ref('');
const localDeliveryDate = ref('');
const deliveryDateTouched = ref(false);
const showOrderConfirm = ref(false);
const showClearConfirm = ref(false);
const isAddressFocused = ref(false);

/** Не перезаписывать localAddress с сервера, пока пользователь редактирует поле */
watch(
    () => props.cart?.delivery_address,
    (value) => {
        if (isAddressFocused.value) {
            return;
        }

        localAddress.value = value ?? '';
    },
    { immediate: true },
);

/** Превью даты с сервера; в manual-режиме после правок / preferred не перезаписывать */
watch(
    () => props.cart?.delivery_date,
    (value) => {
        if (props.manualOrderMode && deliveryDateTouched.value) {
            return;
        }

        localDeliveryDate.value = typeof value === 'string' ? value : '';
    },
    { immediate: true },
);

watch(
    () => props.preferredDeliveryDate,
    (value) => {
        if (!props.manualOrderMode) {
            return;
        }

        if (typeof value !== 'string' || value.trim() === '') {
            return;
        }

        localDeliveryDate.value = value.trim();
        deliveryDateTouched.value = true;
    },
    { immediate: true },
);

watch(
    () => props.cart,
    (cart) => {
        if (!cart) {
            deliveryDateTouched.value = false;
            localDeliveryDate.value = '';
        }
    },
);

const cartGroups = computed(() => buildCartGroups(props.cart));

const isEmpty = computed(() => !props.cart || cartGroups.value.length === 0);

const deliveryApplicable = computed(() => props.cart?.delivery_applicable === true);

const hasDeliveryHint = computed(
    () => deliveryApplicable.value && props.cart?.amount_to_next_tier != null,
);

/** Отступ под fixed footer: выше блок итогов при delivery_applicable и tier-hint */
const footerBottomPaddingClass = computed(() => {
    if (isEmpty.value || props.loading || !props.cart) {
        return '';
    }

    if (deliveryApplicable.value) {
        return hasDeliveryHint.value ? 'pb-56' : 'pb-48';
    }

    return 'pb-36';
});

const hasAddress = computed(() => localAddress.value.trim().length > 0);

const hasDeliveryDate = computed(() => {
    if (!props.manualOrderMode) {
        return true;
    }

    return localDeliveryDate.value.trim().length > 0;
});

const canSubmit = computed(
    () => hasAddress.value
        && hasDeliveryDate.value
        && !props.submitting
        && !props.savingAddress,
);

function handleAddressFocus() {
    isAddressFocused.value = true;
}

function handleAddressInput(value) {
    localAddress.value = value;
    emit('delivery-address-input', value);
}

function handleAddressBlur(value) {
    isAddressFocused.value = false;
    localAddress.value = value;
    emit('delivery-address-blur', value);
}

function handleDeliveryDateInput(value) {
    deliveryDateTouched.value = true;
    localDeliveryDate.value = value;
}

function openOrderConfirm() {
    if (!canSubmit.value) {
        return;
    }

    showOrderConfirm.value = true;
}

function closeOrderConfirm() {
    if (!props.submitting) {
        showOrderConfirm.value = false;
    }
}

function confirmOrder() {
    emit(
        'submit-order',
        localAddress.value,
        props.manualOrderMode ? localDeliveryDate.value.trim() : null,
    );
}

function openClearConfirm() {
    if (props.clearing || props.submitting || props.savingAddress || isEmpty.value) {
        return;
    }

    showClearConfirm.value = true;
}

function closeClearConfirm() {
    if (!props.clearing) {
        showClearConfirm.value = false;
    }
}

function confirmClearCart() {
    emit('clear-cart');
}

/**
 * Перехват «Назад» из App.vue: сначала закрыть открытую модалку.
 * @returns {boolean} true — событие обработано, навигацию не продолжать
 */
function handleBackRequest() {
    if (showClearConfirm.value) {
        closeClearConfirm();

        return true;
    }

    if (showOrderConfirm.value) {
        closeOrderConfirm();

        return true;
    }

    return false;
}

function handleGoBack() {
    if (!handleBackRequest()) {
        emit('go-back');
    }
}

defineExpose({ handleBackRequest });

watch(
    () => props.submitting,
    (submitting, wasSubmitting) => {
        if (wasSubmitting && !submitting && props.error) {
            showOrderConfirm.value = false;
        }
    },
);

watch(
    () => props.clearing,
    (clearing, wasClearing) => {
        if (wasClearing && !clearing) {
            showClearConfirm.value = false;
        }
    },
);
</script>

<template>
    <div
        class="flex flex-col bg-white"
        :class="[
            footerBottomPaddingClass,
            manualOrderMode ? 'h-full min-h-0 overflow-hidden' : 'min-h-dvh',
        ]"
    >
        <CartHeader
            :delivery-address="localAddress"
            :loading="loading"
            :submitting="submitting"
            :clearing="clearing"
            :saving-address="savingAddress"
            :is-empty="isEmpty"
            :orders-unread-count="ordersUnreadCount"
            :manual-order-mode="manualOrderMode"
            class="shrink-0"
            @go-back="handleGoBack"
            @open-orders="emit('open-orders')"
            @clear-cart="openClearConfirm"
            @update:delivery-address="localAddress = $event"
            @delivery-address-focus="handleAddressFocus"
            @delivery-address-input="handleAddressInput"
            @delivery-address-blur="handleAddressBlur"
        />

        <main
            class="px-4 py-4"
            :class="manualOrderMode ? 'min-h-0 flex-1 overflow-y-auto' : 'flex-1'"
        >
            <div v-if="loading" class="flex items-center justify-center py-16">
                <div class="h-8 w-8 animate-spin rounded-full border-4 border-max-primary border-t-transparent" />
            </div>

            <div v-else-if="isEmpty" class="flex flex-col items-center py-16 text-center">
                <div class="mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-gray-100">
                    <EmptyStateIcon name="cart" size="lg" />
                </div>
                <p class="text-base font-medium text-gray-900">Корзина пуста</p>
                <p class="mt-1 text-sm text-max-muted">Добавьте блюда из меню ресторана</p>
                <button
                    type="button"
                    class="mt-6 rounded-2xl bg-max-primary px-6 py-3 text-sm font-medium text-white transition hover:bg-max-primary-hover"
                    @click="emit('go-to-restaurants')"
                >
                    {{ isSingleRestaurantMode ? 'К меню' : 'К ресторанам' }}
                </button>
            </div>

            <template v-else>
                <div
                    v-if="error"
                    class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"
                >
                    {{ error }}
                </div>

                <CartItemList
                    :cart-groups="cartGroups"
                    :updating-item-id="updatingItemId"
                    @update-quantity="(item, quantity) => emit('update-quantity', item, quantity)"
                    @remove-item="(item) => emit('remove-item', item)"
                />
            </template>
        </main>

        <CartSummaryFooter
            v-if="!isEmpty && !loading && cart"
            :cart="cart"
            :delivery-applicable="deliveryApplicable"
            :can-submit="canSubmit"
            :has-address="hasAddress"
            :saving-address="savingAddress"
            :editable-delivery-date="manualOrderMode"
            :delivery-date="localDeliveryDate"
            :has-delivery-date="hasDeliveryDate"
            @update:delivery-date="handleDeliveryDateInput"
            @submit="openOrderConfirm"
        />

        <CartOrderConfirmModal
            :open="showOrderConfirm"
            :cart-groups="cartGroups"
            :delivery-address="localAddress"
            :delivery-date="manualOrderMode ? localDeliveryDate : (cart?.delivery_date ?? '')"
            :cart="cart"
            :delivery-applicable="deliveryApplicable"
            :submitting="submitting"
            @close="closeOrderConfirm"
            @confirm="confirmOrder"
        />

        <ConfirmDeleteModal
            :open="showClearConfirm"
            title="Очистить корзину?"
            message="Все позиции будут удалены. Это действие нельзя отменить."
            confirm-label="Очистить"
            loading-label="Очистка…"
            :loading="clearing"
            @close="closeClearConfirm"
            @confirm="confirmClearCart"
        />
    </div>
</template>
