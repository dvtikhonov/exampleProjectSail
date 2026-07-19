<script setup>
/**
 * Экран корзины: позиции, адрес доставки, итоги, подтверждение заявки.
 *
 * Scope (см. ../components/cart/cartScope.js):
 * - header (в т.ч. адрес доставки), список позиций, fixed footer с итогами, модалка подтверждения
 *
 * Вне scope — не добавлять на этот экран:
 * - upsell «Добавить к заказу?», блок «Акции»
 * - OrderChatPanel (чат — OrderDetailPage после submit)
 * - API /api/food/cart/messages
 *
 * Адрес синхронизируется с сервером через debounce (родитель App.vue).
 * Модалка подтверждения перехватывает кнопку «Назад» через defineExpose.
 */
import { computed, ref, watch } from 'vue';
import CartHeader from '../components/cart/CartHeader.vue';
import CartItemList from '../components/cart/CartItemList.vue';
import CartOrderConfirmModal from '../components/cart/CartOrderConfirmModal.vue';
import CartSummaryFooter from '../components/cart/CartSummaryFooter.vue';
import { buildCartGroups } from '../utils/cartGroups';

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
const showOrderConfirm = ref(false);
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

const canSubmit = computed(
    () => hasAddress.value && !props.submitting && !props.savingAddress,
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
    emit('submit-order', localAddress.value);
}

/**
 * Перехват «Назад» из App.vue: сначала закрыть модалку подтверждения.
 * @returns {boolean} true — событие обработано, навигацию не продолжать
 */
function handleBackRequest() {
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
</script>

<template>
    <div class="flex min-h-dvh flex-col bg-white" :class="footerBottomPaddingClass">
        <CartHeader
            :delivery-address="localAddress"
            :loading="loading"
            :submitting="submitting"
            :clearing="clearing"
            :saving-address="savingAddress"
            :is-empty="isEmpty"
            :orders-unread-count="ordersUnreadCount"
            @go-back="handleGoBack"
            @open-orders="emit('open-orders')"
            @clear-cart="emit('clear-cart')"
            @update:delivery-address="localAddress = $event"
            @delivery-address-focus="handleAddressFocus"
            @delivery-address-input="handleAddressInput"
            @delivery-address-blur="handleAddressBlur"
        />

        <main class="flex-1 px-4 py-4">
            <div v-if="loading" class="flex items-center justify-center py-16">
                <div class="h-8 w-8 animate-spin rounded-full border-4 border-max-primary border-t-transparent" />
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
            @submit="openOrderConfirm"
        />

        <CartOrderConfirmModal
            :open="showOrderConfirm"
            :cart-groups="cartGroups"
            :delivery-address="localAddress"
            :cart="cart"
            :delivery-applicable="deliveryApplicable"
            :submitting="submitting"
            @close="closeOrderConfirm"
            @confirm="confirmOrder"
        />
    </div>
</template>
