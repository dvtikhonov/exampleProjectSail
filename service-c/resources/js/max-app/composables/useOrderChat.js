/**
 * Загрузка, отправка и polling сообщений чата по заказу.
 */
import { onMounted, onUnmounted, ref, watch } from 'vue';
import { extractErrorMessage, fetchOrderMessages, sendOrderMessage } from '../api';
import { ORDER_CHAT_POLL_INTERVAL_MS } from '../constants/orderChat';

/**
 * @param {unknown} error
 * @returns {boolean}
 */
function isRequestCanceled(error) {
    if (!error || typeof error !== 'object') {
        return false;
    }

    return (
        /** @type {{ code?: string, name?: string }} */ (error).code === 'ERR_CANCELED'
        || /** @type {{ code?: string, name?: string }} */ (error).name === 'CanceledError'
    );
}

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
    let loadSeq = 0;
    /** @type {AbortController|null} */
    let loadAbortController = null;
    /** @type {AbortController|null} */
    let pollAbortController = null;

    /**
     * @returns {number}
     */
    function resolveOrderId() {
        return typeof orderId === 'function' ? orderId() : orderId.value;
    }

    function abortLoad() {
        if (loadAbortController !== null) {
            loadAbortController.abort();
            loadAbortController = null;
        }
    }

    function abortPoll() {
        if (pollAbortController !== null) {
            pollAbortController.abort();
            pollAbortController = null;
        }
    }

    async function loadMessages() {
        abortLoad();
        abortPoll();

        const controller = new AbortController();
        loadAbortController = controller;
        const mySeq = ++loadSeq;
        const requestedOrderId = resolveOrderId();

        loading.value = true;
        loadError.value = '';

        try {
            const loaded = await fetchOrderMessages(requestedOrderId, {
                signal: controller.signal,
            });

            if (mySeq !== loadSeq) {
                return;
            }

            messages.value = loaded;
            onMessagesRead?.();
        } catch (error) {
            if (isRequestCanceled(error) || mySeq !== loadSeq) {
                return;
            }

            loadError.value = extractErrorMessage(error);
        } finally {
            if (mySeq === loadSeq) {
                loading.value = false;
            }
        }
    }

    /** Инкрементальная подгрузка только сообщений новее lastId */
    async function pollNewMessages() {
        if (loading.value || messages.value.length === 0) {
            return false;
        }

        abortPoll();

        const controller = new AbortController();
        pollAbortController = controller;
        const orderIdAtStart = resolveOrderId();
        const lastId = messages.value[messages.value.length - 1].id;

        try {
            const newMessages = await fetchOrderMessages(orderIdAtStart, {
                afterId: lastId,
                signal: controller.signal,
            });

            if (resolveOrderId() !== orderIdAtStart) {
                return false;
            }

            if (newMessages.length > 0) {
                messages.value = [...messages.value, ...newMessages];
                onMessagesRead?.();

                return true;
            }
        } catch {
            // Ошибки polling (включая отмену) не перекрывают уже загруженную ленту.
        }

        return false;
    }

    async function sendMessage() {
        const trimmed = body.value.trim();

        if (trimmed === '' || sending.value) {
            return false;
        }

        const orderIdAtStart = resolveOrderId();

        sending.value = true;
        sendError.value = '';

        try {
            const message = await sendOrderMessage(orderIdAtStart, trimmed);

            if (resolveOrderId() !== orderIdAtStart) {
                return false;
            }

            messages.value = [...messages.value, message];
            body.value = '';

            return true;
        } catch (error) {
            if (isRequestCanceled(error)) {
                return false;
            }

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
        abortLoad();
        abortPoll();
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
