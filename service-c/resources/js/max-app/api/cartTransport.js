/**
 * Единый транспорт корзины: client (/food/cart) и manual (/food/admin/manual-orders/cart).
 * Composables работают с одним интерфейсом без if (manual) на каждый метод.
 *
 * @typedef {import('./types.js').CartDto} CartDto
 * @typedef {import('./types.js').CartEnvelope} CartEnvelope
 * @typedef {import('./types.js').OrderDto} OrderDto
 */
import { addComboWithRollback } from './cartHelpers';
import {
    addToCart,
    clearCart,
    fetchCart,
    removeCartItem,
    submitOrder,
    updateCartDeliveryAddress,
    updateCartItem,
} from './cart';
import {
    addToManualCart,
    clearManualCart,
    fetchManualCart,
    removeManualCartItem,
    submitManualOrder,
    updateManualCartDeliveryAddress,
    updateManualCartItem,
} from './manualOrders';

/**
 * @typedef {{ comboRef?: string|null, comboPartnerDishId?: number|null }} CartAddItemOptions
 */

/**
 * @typedef {object} CartTransport
 * @property {boolean} includeUnavailableInMenu — для menu API в manual-режиме
 * @property {() => Promise<CartEnvelope>} fetch
 * @property {(dishId: number, quantity?: number, options?: CartAddItemOptions) => Promise<CartDto>} addItem
 * @property {(firstDishId: number, secondDishId: number, quantity: number, comboRef: string) => Promise<CartDto>} addCombo
 * @property {(itemId: number, quantity: number) => Promise<CartDto>} updateItem
 * @property {(itemId: number) => Promise<CartDto>} removeItem
 * @property {() => Promise<CartDto|null>} clear
 * @property {(address: string) => Promise<CartEnvelope>} updateDeliveryAddress
 * @property {(deliveryDate?: string|null) => Promise<OrderDto>} submit
 */

export { addComboWithRollback } from './cartHelpers.js';

/**
 * @returns {CartTransport}
 */
export function createClientCartTransport() {
    const addItem = (dishId, quantity = 1, options = {}) => addToCart(dishId, quantity, options);
    const removeItem = (itemId) => removeCartItem(itemId);

    return {
        includeUnavailableInMenu: false,
        fetch: () => fetchCart(),
        addItem,
        addCombo: (firstDishId, secondDishId, quantity, comboRef) => addComboWithRollback({
            addItem,
            removeItem,
            firstDishId,
            secondDishId,
            quantity,
            comboRef,
        }),
        updateItem: (itemId, quantity) => updateCartItem(itemId, quantity),
        removeItem,
        clear: () => clearCart(),
        updateDeliveryAddress: (address) => updateCartDeliveryAddress(address),
        submit: () => submitOrder(),
    };
}

/**
 * @param {string|null|undefined} deliveryDate
 * @returns {string|null}
 */
function normalizeSubmitDeliveryDate(deliveryDate) {
    if (typeof deliveryDate !== 'string') {
        return null;
    }

    const trimmed = deliveryDate.trim();

    return trimmed !== '' ? trimmed : null;
}

/**
 * @param {(() => number|null)|number} getMaxUserId — id клиента или getter (ручной заказ)
 * @returns {CartTransport}
 */
export function createManualCartTransport(getMaxUserId) {
    /**
     * @returns {number}
     */
    function resolveMaxUserId() {
        const id = typeof getMaxUserId === 'function' ? getMaxUserId() : getMaxUserId;

        if (typeof id !== 'number' || id <= 0) {
            throw new Error('Для ручной корзины нужен max_user_id клиента.');
        }

        return id;
    }

    const addItem = (dishId, quantity = 1, options = {}) => (
        addToManualCart(resolveMaxUserId(), dishId, quantity, options)
    );
    const removeItem = (itemId) => removeManualCartItem(resolveMaxUserId(), itemId);

    return {
        includeUnavailableInMenu: true,
        fetch: () => fetchManualCart(resolveMaxUserId()),
        addItem,
        addCombo: (firstDishId, secondDishId, quantity, comboRef) => addComboWithRollback({
            addItem,
            removeItem,
            firstDishId,
            secondDishId,
            quantity,
            comboRef,
        }),
        updateItem: (itemId, quantity) => updateManualCartItem(resolveMaxUserId(), itemId, quantity),
        removeItem,
        clear: () => clearManualCart(resolveMaxUserId()),
        updateDeliveryAddress: (address) => updateManualCartDeliveryAddress(resolveMaxUserId(), address),
        submit: (deliveryDate = null) => submitManualOrder(
            resolveMaxUserId(),
            normalizeSubmitDeliveryDate(deliveryDate),
        ),
    };
}

/**
 * Фабрика по getTargetMaxUserId: number → manual, иначе client.
 * Для shell’ов с фиксированным режимом предпочтительнее createClient/Manual.
 *
 * @param {(() => number|null)=} getTargetMaxUserId
 * @returns {CartTransport}
 */
export function createCartTransport(getTargetMaxUserId = () => null) {
    const id = getTargetMaxUserId();

    if (typeof id === 'number' && id > 0) {
        return createManualCartTransport(getTargetMaxUserId);
    }

    return createClientCartTransport();
}
