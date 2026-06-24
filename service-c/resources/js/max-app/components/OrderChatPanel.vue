<script setup>
import { nextTick, onMounted, onUnmounted, ref, watch } from 'vue';
import { extractErrorMessage, fetchOrderMessages, sendOrderMessage } from '../api/foodClient';
import OrderChatMessage from './OrderChatMessage.vue';

const props = defineProps({
    orderId: {
        type: Number,
        required: true,
    },
    perspective: {
        type: String,
        default: 'customer',
        validator: (value) => ['customer', 'admin'].includes(value),
    },
});

const emit = defineEmits(['messages-read']);

const messages = ref([]);
const loading = ref(true);
const loadError = ref('');
const sending = ref(false);
const sendError = ref('');
const body = ref('');
const messagesContainer = ref(null);

const POLL_INTERVAL_MS = 8000;
const MAX_BODY_LENGTH = 2000;

let pollTimer = null;

function isOwnMessage(message) {
    if (props.perspective === 'customer') {
        return message.author_type === 'customer';
    }

    return message.author_type === 'admin';
}

async function scrollToBottom() {
    await nextTick();

    const container = messagesContainer.value;

    if (container) {
        container.scrollTop = container.scrollHeight;
    }
}

async function loadMessages() {
    loading.value = true;
    loadError.value = '';

    try {
        messages.value = await fetchOrderMessages(props.orderId);
        await scrollToBottom();
        emit('messages-read');
    } catch (error) {
        loadError.value = extractErrorMessage(error);
    } finally {
        loading.value = false;
    }
}

async function pollNewMessages() {
    if (loading.value || messages.value.length === 0) {
        return;
    }

    const lastId = messages.value[messages.value.length - 1].id;

    try {
        const newMessages = await fetchOrderMessages(props.orderId, { afterId: lastId });

        if (newMessages.length > 0) {
            messages.value = [...messages.value, ...newMessages];
            await scrollToBottom();
            emit('messages-read');
        }
    } catch {
        // Ошибки polling не перекрывают уже загруженную ленту.
    }
}

async function handleSend() {
    const trimmed = body.value.trim();

    if (trimmed === '' || sending.value) {
        return;
    }

    sending.value = true;
    sendError.value = '';

    try {
        const message = await sendOrderMessage(props.orderId, trimmed);
        messages.value = [...messages.value, message];
        body.value = '';
        await scrollToBottom();
    } catch (error) {
        sendError.value = extractErrorMessage(error);
    } finally {
        sending.value = false;
    }
}

function handleKeydown(event) {
    if (event.key === 'Enter' && !event.shiftKey) {
        event.preventDefault();
        handleSend();
    }
}

function startPolling() {
    stopPolling();
    pollTimer = setInterval(pollNewMessages, POLL_INTERVAL_MS);
}

function stopPolling() {
    if (pollTimer !== null) {
        clearInterval(pollTimer);
        pollTimer = null;
    }
}

watch(
    () => props.orderId,
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
</script>

<template>
    <div class="flex min-h-0 flex-1 flex-col rounded-2xl border border-gray-100 bg-gray-50">
        <div class="shrink-0 border-b border-gray-100 bg-white px-4 py-3">
            <h2 class="text-sm font-semibold text-gray-900">Чат по заказу</h2>
            <p class="text-xs text-max-muted">Уточнения и вопросы по заявке</p>
        </div>

        <div
            ref="messagesContainer"
            class="min-h-0 flex-1 space-y-3 overflow-y-auto px-3 py-4"
        >
            <div v-if="loading" class="flex items-center justify-center py-12">
                <div class="h-7 w-7 animate-spin rounded-full border-4 border-max-primary border-t-transparent" />
            </div>

            <div
                v-else-if="loadError"
                class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"
            >
                {{ loadError }}
                <button
                    type="button"
                    class="mt-2 block font-medium text-red-800 underline"
                    @click="loadMessages"
                >
                    Повторить
                </button>
            </div>

            <p
                v-else-if="messages.length === 0"
                class="py-8 text-center text-sm text-max-muted"
            >
                Сообщений пока нет. Напишите, если нужно что-то уточнить.
            </p>

            <OrderChatMessage
                v-for="message in messages"
                :key="message.id"
                :message="message"
                :is-own="isOwnMessage(message)"
                :perspective="perspective"
            />
        </div>

        <div class="shrink-0 border-t border-gray-200 bg-white px-3 py-3 safe-area-bottom">
            <div
                v-if="sendError"
                class="mb-2 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-xs text-red-700"
            >
                {{ sendError }}
            </div>

            <div class="flex items-end gap-2">
                <textarea
                    v-model="body"
                    rows="2"
                    :maxlength="MAX_BODY_LENGTH"
                    :disabled="loading || !!loadError || sending"
                    placeholder="Ваше сообщение…"
                    class="min-h-[44px] flex-1 resize-none rounded-xl border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm text-gray-900 placeholder:text-max-muted focus:border-max-primary focus:bg-white focus:outline-none focus:ring-1 focus:ring-max-primary disabled:opacity-50"
                    @keydown="handleKeydown"
                />
                <button
                    type="button"
                    class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-max-primary text-white transition hover:bg-max-primary-hover disabled:opacity-50"
                    :disabled="loading || !!loadError || sending || body.trim() === ''"
                    aria-label="Отправить"
                    @click="handleSend"
                >
                    <svg
                        v-if="!sending"
                        class="h-5 w-5"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke="currentColor"
                        stroke-width="2"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"
                        />
                    </svg>
                    <div
                        v-else
                        class="h-5 w-5 animate-spin rounded-full border-2 border-white border-t-transparent"
                    />
                </button>
            </div>
        </div>
    </div>
</template>
