/**
 * Загрузка, отправка и polling сообщений лички с пользователем через бота.
 */
import { onMounted, onUnmounted, ref, watch } from 'vue';
import { extractErrorMessage, fetchBotDmMessages, sendBotDmMessage } from '../api';
import { BOT_DM_CHAT_POLL_INTERVAL_MS } from '../constants/botDmChat';

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
 * @param {import('vue').Ref<number|null>|(() => number|null)} options.maxUserId
 * @returns {object}
 */
export function useBotDmChat({ maxUserId }) {
    const messages = ref([]);
    const loading = ref(false);
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
     * @returns {number|null}
     */
    function resolveMaxUserId() {
        const value = typeof maxUserId === 'function' ? maxUserId() : maxUserId.value;
        const id = Number(value);

        return Number.isFinite(id) && id > 0 ? id : null;
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

    function resetState() {
        messages.value = [];
        loading.value = false;
        loadError.value = '';
        sending.value = false;
        sendError.value = '';
        body.value = '';
    }

    async function loadMessages() {
        abortLoad();
        abortPoll();

        const requestedMaxUserId = resolveMaxUserId();

        if (requestedMaxUserId === null) {
            resetState();

            return;
        }

        const controller = new AbortController();
        loadAbortController = controller;
        const mySeq = ++loadSeq;

        loading.value = true;
        loadError.value = '';

        try {
            const loaded = await fetchBotDmMessages(requestedMaxUserId, {
                signal: controller.signal,
            });

            if (mySeq !== loadSeq) {
                return;
            }

            messages.value = loaded;
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
        const currentMaxUserId = resolveMaxUserId();

        if (currentMaxUserId === null || loading.value || messages.value.length === 0) {
            return false;
        }

        abortPoll();

        const controller = new AbortController();
        pollAbortController = controller;
        const lastId = messages.value[messages.value.length - 1].id;

        try {
            const newMessages = await fetchBotDmMessages(currentMaxUserId, {
                afterId: lastId,
                signal: controller.signal,
            });

            if (resolveMaxUserId() !== currentMaxUserId) {
                return false;
            }

            if (newMessages.length > 0) {
                messages.value = [...messages.value, ...newMessages];

                return true;
            }
        } catch {
            // Ошибки polling (включая отмену) не перекрывают уже загруженную ленту.
        }

        return false;
    }

    async function sendMessage() {
        const trimmed = body.value.trim();
        const currentMaxUserId = resolveMaxUserId();

        if (trimmed === '' || sending.value || currentMaxUserId === null) {
            return false;
        }

        sending.value = true;
        sendError.value = '';

        try {
            const message = await sendBotDmMessage(currentMaxUserId, trimmed);

            if (resolveMaxUserId() !== currentMaxUserId) {
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
        }, BOT_DM_CHAT_POLL_INTERVAL_MS);
    }

    function stopPolling() {
        if (pollTimer !== null) {
            clearInterval(pollTimer);
            pollTimer = null;
        }
    }

    watch(
        () => resolveMaxUserId(),
        async () => {
            messages.value = [];
            body.value = '';
            sendError.value = '';
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
