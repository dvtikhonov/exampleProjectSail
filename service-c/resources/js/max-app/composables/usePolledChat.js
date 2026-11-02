/**
 * Общий composable: загрузка, отправка и polling ленты сообщений.
 */
import { onMounted, onUnmounted, ref, watch } from 'vue';
import { extractErrorMessage } from '../api';

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
 * Склеивает ленту по id: входящая запись заменяет совпадение, порядок — по возрастанию id.
 * Опрос и отправка могут принести одну и ту же запись, пока запрос опроса ещё в полёте.
 *
 * @param {Array<{ id?: number|null }>} current
 * @param {Array<{ id?: number|null }>} incoming
 * @returns {Array<{ id: number }>}
 */
function mergeMessages(current, incoming) {
    const byId = new Map(current.map((message) => [message.id, message]));

    for (const message of incoming) {
        if (message?.id != null) {
            byId.set(message.id, message);
        }
    }

    return [...byId.values()].sort((a, b) => a.id - b.id);
}

/**
 * @param {object} options
 * @param {() => number|null} options.resolveId — текущий id сущности чата; null = нет активного чата
 * @param {(
 *   id: number,
 *   opts: { afterId?: number|null, signal?: AbortSignal }
 * ) => Promise<object[]>} options.fetch
 * @param {(id: number, body: string) => Promise<object>} options.send
 * @param {number} options.pollIntervalMs
 * @param {() => void} [options.onMessagesRead]
 * @param {boolean} [options.initialLoading=false]
 * @param {boolean} [options.clearComposerOnIdChange=false]
 * @returns {object}
 */
export function usePolledChat({
    resolveId,
    fetch: fetchMessages,
    send: sendFn,
    pollIntervalMs,
    onMessagesRead,
    initialLoading = false,
    clearComposerOnIdChange = false,
}) {
    const messages = ref([]);
    const loading = ref(initialLoading);
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

        const requestedId = resolveId();

        if (requestedId === null) {
            resetState();

            return;
        }

        const controller = new AbortController();
        loadAbortController = controller;
        const mySeq = ++loadSeq;

        loading.value = true;
        loadError.value = '';

        try {
            const loaded = await fetchMessages(requestedId, {
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

    /** Подгрузка новых сообщений. Пустая лента опрашивается тем же fetch без after_id. */
    async function pollNewMessages() {
        const idAtStart = resolveId();

        if (idAtStart === null || loading.value) {
            return false;
        }

        abortPoll();

        const controller = new AbortController();
        pollAbortController = controller;
        const lastId = messages.value.length > 0
            ? messages.value[messages.value.length - 1].id
            : null;

        try {
            const newMessages = await fetchMessages(idAtStart, {
                afterId: lastId,
                signal: controller.signal,
            });

            if (resolveId() !== idAtStart || newMessages.length === 0) {
                return false;
            }

            messages.value = mergeMessages(messages.value, newMessages);
            onMessagesRead?.();

            return true;
        } catch {
            // Ошибки polling (включая отмену) не перекрывают уже загруженную ленту.
        }

        return false;
    }

    async function sendMessage() {
        const trimmed = body.value.trim();
        const idAtStart = resolveId();

        if (trimmed === '' || sending.value || idAtStart === null) {
            return false;
        }

        sending.value = true;
        sendError.value = '';

        try {
            const message = await sendFn(idAtStart, trimmed);

            if (resolveId() !== idAtStart) {
                return false;
            }

            messages.value = mergeMessages(messages.value, [message]);
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
        }, pollIntervalMs);
    }

    function stopPolling() {
        if (pollTimer !== null) {
            clearInterval(pollTimer);
            pollTimer = null;
        }
    }

    watch(
        () => resolveId(),
        async () => {
            messages.value = [];

            if (clearComposerOnIdChange) {
                body.value = '';
                sendError.value = '';
            }

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
