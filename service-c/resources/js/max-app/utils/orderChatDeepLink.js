/**
 * Deep link на чат заказа из уведомления MAX.
 *
 * Источники (по приоритету):
 * 1) start_param кнопки open_app: order_{id}_chat
 * 2) query локального браузера: ?order_id=N&view=chat
 */

/**
 * @param {string|null|undefined} startParam
 * @returns {number|null}
 */
export function parseOrderIdFromStartParam(startParam) {
    if (typeof startParam !== 'string' || startParam === '') {
        return null;
    }

    const match = /^order_(\d+)_chat$/.exec(startParam.trim());

    if (!match) {
        return null;
    }

    const orderId = Number.parseInt(match[1], 10);

    return Number.isNaN(orderId) ? null : orderId;
}

/**
 * @param {string|URLSearchParams} search
 * @returns {number|null}
 */
export function parseOrderIdFromQuery(search) {
    const params = search instanceof URLSearchParams
        ? search
        : new URLSearchParams(search);
    const orderId = params.get('order_id');
    const view = params.get('view');

    if (!orderId || view !== 'chat') {
        return null;
    }

    const parsed = Number.parseInt(orderId, 10);

    return Number.isNaN(parsed) ? null : parsed;
}

/**
 * @param {{ getStartParam?: () => string, search?: string }} [sources]
 * @returns {number|null}
 */
export function resolveOrderChatDeepLinkOrderId(sources = {}) {
    const startParam = typeof sources.getStartParam === 'function'
        ? sources.getStartParam()
        : '';

    const fromStartParam = parseOrderIdFromStartParam(startParam);

    if (fromStartParam !== null) {
        return fromStartParam;
    }

    const search = sources.search ?? (typeof window !== 'undefined' ? window.location.search : '');

    return parseOrderIdFromQuery(search);
}
