/**
 * Корзина: загрузка, изменение позиций, адрес доставки, оформление заказа.
 */
import { computed, onScopeDispose, ref, watch } from 'vue';
import {
    clearCart,
    extractErrorMessage,
    fetchCart,
    removeCartItem,
    submitOrder,
    updateCartDeliveryAddress,
    updateCartItem,
} from '../api/foodClient';
import { VIEWS } from '../constants/views';
import { buildCartGroups, countCartGroupsQuantity } from '../utils/cartGroups';

/** Задержка debounce автосохранения адреса (мс) */
const ADDRESS_DEBOUNCE_MS = 500;

/**
 * @param {object} deps
 * @param {import('vue').Ref<string>} deps.currentView — для перехода на экран подтверждения
 */
export function useCart({ currentView }) {
    const cart = ref(null);
    /** Сохранённый адрес профиля — показывается в меню даже без корзины */
    const savedDeliveryAddress = ref('');
    const cartLoading = ref(false);
    const cartError = ref('');
    const updatingItemId = ref(null);
    const clearingCart = ref(false);
    const submitting = ref(false);
    const savingAddress = ref(false);
    const submittedOrder = ref(null);
    const cartPageRef = ref(null);

    /** Таймер debounce для автосохранения адреса доставки */
    let addressDebounceTimer = null;

    const cartItemCount = computed(() => {
        return countCartGroupsQuantity(cart.value);
    });

    const cartTotal = computed(() => cart.value?.total ?? '0.00');

    /** Адрес для шапки меню и корзины: из корзины или из профиля */
    const deliveryAddress = computed(() => {
        const fromCart = cart.value?.delivery_address?.trim() ?? '';

        if (fromCart !== '') {
            return fromCart;
        }

        return savedDeliveryAddress.value.trim();
    });

    watch(
        cart,
        (value) => {
            rememberDeliveryAddress(value?.delivery_address);
        },
        { flush: 'sync' },
    );

    function rememberDeliveryAddress(address) {
        const trimmed = typeof address === 'string' ? address.trim() : '';

        if (trimmed !== '') {
            savedDeliveryAddress.value = trimmed;
        }
    }

    function applyCartEnvelope(envelope) {
        cart.value = envelope.cart ?? null;
        rememberDeliveryAddress(envelope.deliveryAddress ?? envelope.cart?.delivery_address);
    }

    function clearAddressDebounceTimer() {
        if (addressDebounceTimer !== null) {
            clearTimeout(addressDebounceTimer);
            addressDebounceTimer = null;
        }
    }

    async function loadCart() {
        cartLoading.value = true;
        cartError.value = '';

        try {
            applyCartEnvelope(await fetchCart());
        } catch (error) {
            cartError.value = extractErrorMessage(error);
        } finally {
            cartLoading.value = false;
        }
    }

    async function handleUpdateQuantity(item, quantity) {
        const items = item.items ?? [item];
        updatingItemId.value = item.key ?? item.id;

        try {
            for (const cartItem of items) {
                cart.value = await updateCartItem(cartItem.id, quantity);
                rememberDeliveryAddress(cart.value?.delivery_address);
            }
        } catch (error) {
            cartError.value = extractErrorMessage(error);
        } finally {
            updatingItemId.value = null;
        }
    }

    async function handleRemoveItem(item) {
        const items = item.items ?? [item];
        updatingItemId.value = item.key ?? item.id;

        try {
            for (const cartItem of items) {
                cart.value = await removeCartItem(cartItem.id);
                rememberDeliveryAddress(cart.value?.delivery_address);
            }
        } catch (error) {
            cartError.value = extractErrorMessage(error);
        } finally {
            updatingItemId.value = null;
        }
    }

    async function handleClearCart() {
        clearingCart.value = true;
        cartError.value = '';

        try {
            cart.value = await clearCart();
        } catch (error) {
            cartError.value = extractErrorMessage(error);
        } finally {
            clearingCart.value = false;
        }
    }

    async function saveDeliveryAddress(address) {
        const trimmed = address.trim();

        if (trimmed === '') {
            cartError.value = '';
            return;
        }

        savingAddress.value = true;
        cartError.value = '';

        try {
            applyCartEnvelope(await updateCartDeliveryAddress(trimmed));
        } catch (error) {
            cartError.value = extractErrorMessage(error);
        } finally {
            savingAddress.value = false;
        }
    }

    /** Отложенное сохранение адреса при вводе (не блокирует UI на каждый символ) */
    function handleDeliveryAddressInput(address) {
        if (addressDebounceTimer !== null) {
            clearTimeout(addressDebounceTimer);
        }

        addressDebounceTimer = setTimeout(() => {
            addressDebounceTimer = null;
            saveDeliveryAddress(address);
        }, ADDRESS_DEBOUNCE_MS);
    }

    function handleDeliveryAddressBlur(address) {
        clearAddressDebounceTimer();
        saveDeliveryAddress(address);
    }

    async function handleSubmitOrder(deliveryAddressValue) {
        clearAddressDebounceTimer();

        const trimmed = deliveryAddressValue.trim();

        if (trimmed === '') {
            cartError.value = 'Укажите адрес доставки.';
            return;
        }

        submitting.value = true;
        cartError.value = '';

        try {
            applyCartEnvelope(await updateCartDeliveryAddress(trimmed));
            submittedOrder.value = await submitOrder();
            rememberDeliveryAddress(trimmed);
            cart.value = null;
            currentView.value = VIEWS.confirmation;
        } catch (error) {
            cartError.value = extractErrorMessage(error);
        } finally {
            submitting.value = false;
        }
    }

    /** Сброс оформленного заказа при возврате на главный экран */
    function resetSubmittedOrder() {
        submittedOrder.value = null;
    }

    onScopeDispose(() => {
        clearAddressDebounceTimer();
    });

    return {
        cart,
        savedDeliveryAddress,
        deliveryAddress,
        cartLoading,
        cartError,
        updatingItemId,
        clearingCart,
        submitting,
        savingAddress,
        submittedOrder,
        cartPageRef,
        cartGroups: computed(() => buildCartGroups(cart.value)),
        cartItemCount,
        cartTotal,
        loadCart,
        handleUpdateQuantity,
        handleRemoveItem,
        handleClearCart,
        handleDeliveryAddressInput,
        handleDeliveryAddressBlur,
        handleSubmitOrder,
        resetSubmittedOrder,
    };
}