/**
 * Админ: ручные заказы (max_manager) — список, пользователи, корзина, submit.
 *
 * @typedef {import('./types.js').ManualOrderUserDto} ManualOrderUserDto
 * @typedef {import('./types.js').CartDto} CartDto
 * @typedef {import('./types.js').CartEnvelope} CartEnvelope
 * @typedef {import('./types.js').OrderDto} OrderDto
 * @typedef {import('./types.js').AdminOrderListItemDto} AdminOrderListItemDto
 * @typedef {import('./types.js').AdminOrderDetailDto} AdminOrderDetailDto
 */
import { addComboWithRollback } from './cartHelpers';
import { client } from './http';

/**
 * Список ручных заказов с фильтром по периоду, статусу и/или ФИО.
 *
 * @param {{ q?: string, maxUserId?: number|null, dateFrom?: string|null, dateTo?: string|null, status?: string|null, perPage?: number }} [options]
 * @returns {Promise<{ orders: AdminOrderListItemDto[], meta: { current_page: number, per_page: number, total: number, last_page: number, total_amount: string } }>}
 */
export async function fetchManualOrders({
    q = '',
    maxUserId = null,
    dateFrom = null,
    dateTo = null,
    status = null,
    perPage = 30,
} = {}) {
    const params = { per_page: perPage };

    if (typeof q === 'string' && q.trim() !== '') {
        params.q = q.trim();
    }

    if (maxUserId !== null && Number.isFinite(Number(maxUserId)) && Number(maxUserId) > 0) {
        params.max_user_id = Number(maxUserId);
    }

    if (typeof dateFrom === 'string' && dateFrom !== '') {
        params.date_from = dateFrom;
    }

    if (typeof dateTo === 'string' && dateTo !== '') {
        params.date_to = dateTo;
    }

    if (typeof status === 'string' && status !== '') {
        params.status = status;
    }

    const { data } = await client.get('/food/admin/manual-orders', { params });

    return {
        orders: Array.isArray(data.orders) ? data.orders : [],
        meta: {
            current_page: 1,
            per_page: perPage,
            total: 0,
            last_page: 1,
            total_amount: '0.00',
            ...(data.meta ?? {}),
        },
    };
}

/**
 * Детальный просмотр ручного заказа.
 *
 * @param {number} orderId
 * @returns {Promise<AdminOrderDetailDto>}
 */
export async function fetchManualOrder(orderId) {
    const { data } = await client.get(`/food/admin/manual-orders/${orderId}`);

    return data.order;
}

/**
 * @param {{ q?: string, perPage?: number }} [options]
 * @returns {Promise<ManualOrderUserDto[]>}
 */
export async function fetchManualOrderUsers({ q = '', perPage = 30 } = {}) {
    const params = { per_page: perPage };

    if (typeof q === 'string' && q.trim() !== '') {
        params.q = q.trim();
    }

    const { data } = await client.get('/food/admin/manual-orders/users', { params });

    if (Array.isArray(data.users)) {
        return data.users;
    }

    if (Array.isArray(data)) {
        return data;
    }

    return [];
}

/**
 * @param {number} maxUserId
 * @returns {Promise<CartEnvelope>}
 */
export async function fetchManualCart(maxUserId) {
    const { data } = await client.get('/food/admin/manual-orders/cart', {
        params: { max_user_id: maxUserId },
    });

    return {
        cart: data.cart ?? null,
        deliveryAddress: data.delivery_address ?? data.cart?.delivery_address ?? null,
    };
}

/**
 * @param {number} maxUserId
 * @param {number} dishId
 * @param {number} quantity
 * @param {{ comboRef?: string|null, comboPartnerDishId?: number|null }} [options]
 * @returns {Promise<CartDto>}
 */
export async function addToManualCart(
    maxUserId,
    dishId,
    quantity = 1,
    { comboRef = null, comboPartnerDishId = null } = {},
) {
    const payload = {
        max_user_id: maxUserId,
        dish_id: dishId,
        quantity,
    };

    if (comboRef !== null && comboPartnerDishId !== null) {
        payload.combo_ref = comboRef;
        payload.combo_partner_dish_id = comboPartnerDishId;
    }

    const { data } = await client.post('/food/admin/manual-orders/cart/items', payload);

    return data.cart;
}

/**
 * @param {number} maxUserId
 * @param {number} firstDishId
 * @param {number} secondDishId
 * @param {number} quantity
 * @param {string} comboRef
 * @returns {Promise<CartDto>}
 */
export async function addComboToManualCart(maxUserId, firstDishId, secondDishId, quantity, comboRef) {
    return addComboWithRollback({
        addItem: (dishId, qty, options) => addToManualCart(maxUserId, dishId, qty, options),
        removeItem: (itemId) => removeManualCartItem(maxUserId, itemId),
        firstDishId,
        secondDishId,
        quantity,
        comboRef,
    });
}

/**
 * @param {number} maxUserId
 * @param {number} itemId
 * @param {number} quantity
 * @returns {Promise<CartDto>}
 */
export async function updateManualCartItem(maxUserId, itemId, quantity) {
    const { data } = await client.patch(`/food/admin/manual-orders/cart/items/${itemId}`, {
        max_user_id: maxUserId,
        quantity,
    });

    return data.cart;
}

/**
 * @param {number} maxUserId
 * @param {number} itemId
 * @returns {Promise<CartDto>}
 */
export async function removeManualCartItem(maxUserId, itemId) {
    const { data } = await client.delete(`/food/admin/manual-orders/cart/items/${itemId}`, {
        data: { max_user_id: maxUserId },
        params: { max_user_id: maxUserId },
    });

    return data.cart;
}

/**
 * @param {number} maxUserId
 * @returns {Promise<CartDto|null>}
 */
export async function clearManualCart(maxUserId) {
    const { data } = await client.delete('/food/admin/manual-orders/cart', {
        data: { max_user_id: maxUserId },
        params: { max_user_id: maxUserId },
    });

    return data.cart;
}

/**
 * @param {number} maxUserId
 * @param {string} address
 * @returns {Promise<CartEnvelope>}
 */
export async function updateManualCartDeliveryAddress(maxUserId, address) {
    const { data } = await client.patch('/food/admin/manual-orders/cart', {
        max_user_id: maxUserId,
        delivery_address: address,
    });

    return {
        cart: data.cart ?? null,
        deliveryAddress: data.delivery_address ?? data.cart?.delivery_address ?? address,
    };
}

/**
 * @param {number} maxUserId
 * @returns {Promise<OrderDto>}
 */
export async function submitManualOrder(maxUserId) {
    const { data } = await client.post('/food/admin/manual-orders/submit', {
        max_user_id: maxUserId,
    });

    return data.order;
}

/**
 * Переводит черновик после сканирования в статус «Выполнен».
 *
 * @param {number} orderId
 * @returns {Promise<AdminOrderDetailDto>}
 */
export async function completeDraftAfterScanningOrder(orderId) {
    const { data } = await client.post(`/food/admin/manual-orders/${orderId}/complete`);

    return data.order;
}

/**
 * Переносит позиции черновика после сканирования в ручную корзину клиента.
 *
 * @param {number} orderId
 * @returns {Promise<import('./types.js').DraftAfterScanningMoveToCartResult>}
 */
export async function moveDraftAfterScanningOrderToCart(orderId) {
    const { data } = await client.post(`/food/admin/manual-orders/${orderId}/move-to-cart`);
    const rawCustomerId = data.customer?.max_user_id ?? data.customer_max_user_id ?? null;
    const customerMaxUserId = Number(rawCustomerId);

    return {
        cart: data.cart ?? null,
        deliveryAddress: data.delivery_address ?? data.cart?.delivery_address ?? null,
        customerMaxUserId: Number.isFinite(customerMaxUserId) && customerMaxUserId > 0
            ? customerMaxUserId
            : null,
    };
}

/**
 * Удаляет ручной заказ в статусе «Черновик после сканирования».
 *
 * @param {number} orderId
 * @returns {Promise<void>}
 */
export async function deleteManualOrder(orderId) {
    await client.delete(`/food/admin/manual-orders/${orderId}`);
}
