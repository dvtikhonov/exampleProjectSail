/**
 * Заказы клиента и чат по заказу.
 *
 * @typedef {import('./types.js').OrderListItemDto} OrderListItemDto
 * @typedef {import('./types.js').OrderDto} OrderDto
 * @typedef {import('./types.js').OrderMessageDto} OrderMessageDto
 */
import { client } from './http';

/**
 * @typedef {{ current_page: number, per_page: number, total: number, last_page: number }} OrdersMeta
 */

/**
 * @param {{ page?: number, perPage?: number }} [options]
 * @returns {Promise<{ orders: OrderListItemDto[], meta: OrdersMeta }>}
 */
export async function fetchMyOrders({ page = 1, perPage = 20 } = {}) {
    const { data } = await client.get('/food/orders', {
        params: {
            page,
            per_page: perPage,
        },
    });

    return {
        orders: data.orders,
        meta: {
            current_page: data.meta?.current_page ?? page,
            per_page: data.meta?.per_page ?? perPage,
            total: data.meta?.total ?? (Array.isArray(data.orders) ? data.orders.length : 0),
            last_page: data.meta?.last_page ?? 1,
        },
    };
}

/**
 * @param {number} orderId
 * @param {{ signal?: AbortSignal }} [options]
 * @returns {Promise<OrderDto>}
 */
export async function fetchOrder(orderId, { signal } = {}) {
    const { data } = await client.get(`/food/orders/${orderId}`, { signal });

    return data.order;
}

/**
 * @param {number} orderId
 * @param {{ afterId?: number|null, limit?: number, signal?: AbortSignal }} [options]
 * @returns {Promise<OrderMessageDto[]>}
 */
export async function fetchOrderMessages(orderId, { afterId = null, limit = 50, signal } = {}) {
    const params = { limit };

    if (afterId !== null) {
        params.after_id = afterId;
    }

    const { data } = await client.get(`/food/orders/${orderId}/messages`, { params, signal });

    return data.messages;
}

/**
 * @param {number} orderId
 * @param {string} body
 * @param {{ signal?: AbortSignal }} [options]
 * @returns {Promise<OrderMessageDto>}
 */
export async function sendOrderMessage(orderId, body, { signal } = {}) {
    const { data } = await client.post(`/food/orders/${orderId}/messages`, { body }, { signal });

    return data.message;
}
