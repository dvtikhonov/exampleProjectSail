/**
 * Каталог (рестораны/меню) и клиентская корзина /food/cart.
 *
 * @typedef {import('./types.js').RestaurantDto} RestaurantDto
 * @typedef {import('./types.js').MenuDto} MenuDto
 * @typedef {import('./types.js').CartDto} CartDto
 * @typedef {import('./types.js').CartEnvelope} CartEnvelope
 * @typedef {import('./types.js').OrderDto} OrderDto
 */
import { addComboWithRollback } from './cartHelpers';
import { client } from './http';

/**
 * Загружает список ресторанов.
 *
 * @returns {Promise<RestaurantDto[]>}
 */
export async function fetchRestaurants() {
    const { data } = await client.get('/food/restaurants');

    return data.restaurants;
}

/**
 * @param {number} restaurantId
 * @param {{ includeUnavailable?: boolean }} [options]
 * @returns {Promise<MenuDto>}
 */
export async function fetchMenu(restaurantId, options = {}) {
    const params = options.includeUnavailable ? { include_unavailable: 1 } : undefined;
    const { data } = await client.get(`/food/restaurants/${restaurantId}/menu`, { params });

    return data.menu;
}

/**
 * Загружает текущую корзину и сохранённый адрес доставки.
 *
 * @returns {Promise<CartEnvelope>}
 */
export async function fetchCart() {
    const { data } = await client.get('/food/cart');

    return {
        cart: data.cart ?? null,
        deliveryAddress: data.delivery_address ?? data.cart?.delivery_address ?? null,
    };
}

/**
 * @param {number} dishId
 * @param {number} quantity
 * @param {{ comboRef?: string|null, comboPartnerDishId?: number|null }} [options]
 * @returns {Promise<CartDto>}
 */
export async function addToCart(dishId, quantity = 1, { comboRef = null, comboPartnerDishId = null } = {}) {
    const payload = {
        dish_id: dishId,
        quantity,
    };

    if (comboRef !== null && comboPartnerDishId !== null) {
        payload.combo_ref = comboRef;
        payload.combo_partner_dish_id = comboPartnerDishId;
    }

    const { data } = await client.post('/food/cart/items', {
        ...payload,
    });

    return data.cart;
}

/**
 * @param {number} firstDishId
 * @param {number} secondDishId
 * @param {number} quantity
 * @param {string} comboRef
 * @returns {Promise<CartDto>}
 */
export async function addComboToCart(firstDishId, secondDishId, quantity, comboRef) {
    return addComboWithRollback({
        addItem: addToCart,
        removeItem: removeCartItem,
        firstDishId,
        secondDishId,
        quantity,
        comboRef,
    });
}

/**
 * @param {number} itemId
 * @param {number} quantity
 * @returns {Promise<CartDto>}
 */
export async function updateCartItem(itemId, quantity) {
    const { data } = await client.patch(`/food/cart/items/${itemId}`, { quantity });

    return data.cart;
}

/**
 * @param {number} itemId
 * @returns {Promise<CartDto>}
 */
export async function removeCartItem(itemId) {
    const { data } = await client.delete(`/food/cart/items/${itemId}`);

    return data.cart;
}

/**
 * Очищает корзину пользователя.
 *
 * @returns {Promise<CartDto|null>}
 */
export async function clearCart() {
    const { data } = await client.delete('/food/cart');

    return data.cart;
}

/**
 * @param {string} address
 * @returns {Promise<CartEnvelope>}
 */
export async function updateCartDeliveryAddress(address) {
    const { data } = await client.patch('/food/cart', {
        delivery_address: address,
    });

    return {
        cart: data.cart ?? null,
        deliveryAddress: data.delivery_address ?? data.cart?.delivery_address ?? address,
    };
}

/**
 * Оформляет заказ из текущей корзины.
 *
 * @returns {Promise<OrderDto>}
 */
export async function submitOrder() {
    const { data } = await client.post('/food/orders/submit');

    return data.order;
}
