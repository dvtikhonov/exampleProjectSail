/**
 * Загрузка, отправка и polling сообщений чата по заказу.
 */
import { onMounted, onUnmounted, ref, watch } from 'vue';
import { extractErrorMessage, fetchOrderMessages, sendOrderMessage } from '../api';
import { ORDER_CHAT_POLL_INTERVAL_MS } from '../constants/orderChat';

/**
 * @param {object} options
 * @param {import('vue').Ref<number>|(() => number)} options.orderId
 * @param {(payload?: unknown) => void} [options.onMessagesRead]
 * @returns {object}
 */
export function useOrderChat({ orderId, onMessagesRead }) {
    const messages = ref([]);
    const loading = ref(true);
    const loadError = ref('');
    const sending = ref(false);
    const sendError = ref('');
    const body = ref('');

    let pollTimer = null;

    /**
     * @returns {number}
     */
    function resolveOrderId() {
        return typeof orderId === 'function' ? orderId() : orderId.value;
    }

    async function loadMessages() {
        loading.value = true;
        loadError.value = '';

        try {
            messages.value = await fetchOrderMessages(resolveOrderId());
            onMessagesRead?.();
        } catch (error) {
            loadError.value = extractErrorMessage(error);
        } finally {
            loading.value = false;
        }
    }

    /** Инкрементальная подгрузка только сообщений новее lastId */
    async function pollNewMessages() {
        if (loading.value || messages.value.length === 0) {
            return;
        }

        const lastId = messages.value[messages.value.length - 1].id;

        try {
            const newMessages = await fetchOrderMessages(resolveOrderId(), { afterId: lastId });

            if (newMessages.length > 0) {
                messages.value = [...messages.value, ...newMessages];
                onMessagesRead?.();

                return true;
            }
        } catch {
            // Ошибки polling не перекрывают уже загруженную ленту.
        }

        return false;
    }

    async function sendMessage() {
        const trimmed = body.value.trim();

        if (trimmed === '' || sending.value) {
            return false;
        }

        sending.value = true;
        sendError.value = '';

        try {
            const message = await sendOrderMessage(resolveOrderId(), trimmed);
            messages.value = [...messages.value, message];
            body.value = '';

            return true;
        } catch (error) {
            sendError.value = extractErrorMessage(error);

            return false;
        } finally {
            sending.value = false;
        }
    }

    function startPolling() {
        stopPolling();
        pollTimer = setInterval(() => {
            pollNewMessages();
        }, ORDER_CHAT_POLL_INTERVAL_MS);
    }

    function stopPolling() {
        if (pollTimer !== null) {
            clearInterval(pollTimer);
            pollTimer = null;
        }
    }

    watch(
        () => resolveOrderId(),
        async () => {
            messages.value = [];
            await loadMessages();
        },
    );

    onMounted(async () => {
        await loadMessages();
        startPolling();
    });

    onUnmounted(() => {
        stopPolling();
    });

    return {
        messages,
        loading,
        loadError,
        sending,
        sendError,
        body,
        loadMessages,
        pollNewMessages,
        sendMessage,
        startPolling,
        stopPolling,
    };
}
