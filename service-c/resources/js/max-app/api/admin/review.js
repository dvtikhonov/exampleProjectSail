/**
 * Админ: проверка адреса, оплаты и состава заказа.
 *
 * @typedef {import('../types.js').AdminOrderListItemDto} AdminOrderListItemDto
 * @typedef {import('../types.js').AdminOrderDetailDto} AdminOrderDetailDto
 * @typedef {import('../types.js').CompositionUpdateItem} CompositionUpdateItem
 */
import { client } from '../http';

/**
 * @param {'address'|'composition'} scope — adminScope, не adminSection (см. constants/views.js)
 * @param {string} [status]
 * @returns {Promise<AdminOrderListItemDto[]>}
 */
export async function fetchAdminOrders(scope, status = 'pending') {
    const { data } = await client.get('/food/admin/orders', {
        params: { scope, status },
    });

    return data.orders;
}

/**
 * @param {number} orderId
 * @param {'address'|'composition'} scope — adminScope, не adminSection (см. constants/views.js)
 * @returns {Promise<AdminOrderDetailDto>}
 */
export async function fetchAdminOrder(orderId, scope) {
    const { data } = await client.get(`/food/admin/orders/${orderId}`, {
        params: { scope },
    });

    return data.order;
}

/**
 * @param {number} orderId
 * @returns {Promise<AdminOrderDetailDto>}
 */
export async function approveOrderAddress(orderId) {
    const { data } = await client.post(`/food/admin/orders/${orderId}/address/approve`);

    return data.order;
}

/**
 * @param {number} orderId
 * @param {string} comment
 * @returns {Promise<AdminOrderDetailDto>}
 */
export async function rejectOrderAddress(orderId, comment) {
    const { data } = await client.post(`/food/admin/orders/${orderId}/address/reject`, {
        comment,
    });

    return data.order;
}

/**
 * @param {number} orderId
 * @returns {Promise<AdminOrderDetailDto>}
 */
export async function approveOrderPayment(orderId) {
    const { data } = await client.post(`/food/admin/orders/${orderId}/payment/approve`);

    return data.order;
}

/**
 * @param {number} orderId
 * @param {string} comment
 * @returns {Promise<AdminOrderDetailDto>}
 */
export async function rejectOrderPayment(orderId, comment) {
    const { data } = await client.post(`/food/admin/orders/${orderId}/payment/reject`, {
        comment,
    });

    return data.order;
}

/**
 * @param {number} orderId
 * @returns {Promise<AdminOrderDetailDto>}
 */
export async function approveOrderComposition(orderId) {
    const { data } = await client.post(`/food/admin/orders/${orderId}/composition/approve`);

    return data.order;
}

/**
 * @param {number} orderId
 * @param {string} comment
 * @returns {Promise<AdminOrderDetailDto>}
 */
export async function rejectOrderComposition(orderId, comment) {
    const { data } = await client.post(`/food/admin/orders/${orderId}/composition/reject`, {
        comment,
    });

    return data.order;
}

/**
 * @param {number} orderId
 * @param {CompositionUpdateItem[]} items
 * @returns {Promise<AdminOrderDetailDto>}
 */
export async function updateOrderComposition(orderId, items) {
    const { data } = await client.put(`/food/admin/orders/${orderId}/composition`, {
        items,
    });

    return data.order;
}
