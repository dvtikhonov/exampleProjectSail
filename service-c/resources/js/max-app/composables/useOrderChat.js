/**
 * Загрузка, отправка и polling сообщений чата по заказу.
 */
import { fetchOrderMessages, sendOrderMessage } from '../api';
import { ORDER_CHAT_POLL_INTERVAL_MS } from '../constants/orderChat';
import { usePolledChat } from './usePolledChat';

/**
 * @param {object} options
 * @param {import('vue').Ref<number>|(() => number)} options.orderId
 * @param {() => void} [options.onMessagesRead]
 * @returns {object}
 */
export function useOrderChat({ orderId, onMessagesRead }) {
    return usePolledChat({
        resolveId: () => (typeof orderId === 'function' ? orderId() : orderId.value),
        fetch: fetchOrderMessages,
        send: sendOrderMessage,
        pollIntervalMs: ORDER_CHAT_POLL_INTERVAL_MS,
        onMessagesRead,
        initialLoading: true,
    });
}
