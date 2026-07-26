/**
 * Заказы клиента и чат по заказу.
 *
 * @typedef {import('./types.js').OrderListItemDto} OrderListItemDto
 * @typedef {import('./types.js').OrderDto} OrderDto
 * @typedef {import('./types.js').OrderMessageDto} OrderMessageDto
 */
import { client } from './http';

/**
 * @returns {Promise<OrderListItemDto[]>}
 */
export async function fetchMyOrders() {
    const { data } = await client.get('/food/orders');

    return data.orders;
}

/**
 * @param {number} orderId
 * @returns {Promise<OrderDto>}
 */
export async function fetchOrder(orderId) {
    const { data } = await client.get(`/food/orders/${orderId}`);

    return data.order;
}

/**
 * @param {number} orderId
 * @param {{ afterId?: number|null, limit?: number }} [options]
 * @returns {Promise<OrderMessageDto[]>}
 */
export async function fetchOrderMessages(orderId, { afterId = null, limit = 50 } = {}) {
    const params = { limit };

    if (afterId !== null) {
        params.after_id = afterId;
    }

    const { data } = await client.get(`/food/orders/${orderId}/messages`, { params });

    return data.messages;
}

/**
 * @param {number} orderId
 * @param {string} body
 * @returns {Promise<OrderMessageDto>}
 */
export async function sendOrderMessage(orderId, body) {
    const { data } = await client.post(`/food/orders/${orderId}/messages`, { body });

    return data.message;
}
