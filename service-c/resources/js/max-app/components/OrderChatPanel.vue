<script setup>
/**
 * Панель чата по заказу: UI ленты и поля ввода.
 * Загрузка / отправка / polling — в useOrderChat.
 */
import { computed, nextTick, ref, toRef, watch } from 'vue';
import { useAuth } from '../composables/useAuth';
import { useOrderChat } from '../composables/useOrderChat';
import { ORDER_CHAT_MAX_BODY_LENGTH } from '../constants/orderChat';
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
    /** Компактный режим для админки: меньше шапка и поле ввода */
    compact: {
        type: Boolean,
        default: false,
    },
    /** safe-area только на самом нижнем элементе страницы */
    safeAreaBottom: {
        type: Boolean,
        default: true,
    },
    /** Активная зона на карточке заказа (увеличенная доля высоты) */
    active: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['messages-read', 'activate']);

const { maxUserId } = useAuth();
const messagesContainer = ref(null);

/**
 * Равные отступы сверху/снизу у поля ввода; safe-area добавляется к нижнему, не заменяет его.
 * @returns {string}
 */
const composerFooterPadClass = computed(() => {
    if (props.safeAreaBottom) {
        return props.compact
            ? 'pt-2 pb-[calc(0.5rem+env(safe-area-inset-bottom,0px))]'
            : 'pt-3 pb-[calc(0.75rem+env(safe-area-inset-bottom,0px))]';
    }

    return props.compact ? 'pt-2 pb-2' : 'pt-3 pb-3';
});

/** Клик / фокус в панели чата — родитель может увеличить её долю экрана */
function emitActivate() {
    emit('activate');
}

const {
    messages,
    loading,
    loadError,
    sending,
    sendError,
    body,
    loadMessages,
    sendMessage,
} = useOrderChat({
    orderId: toRef(props, 'orderId'),
    onMessagesRead: () => emit('messages-read'),
});

/**
 * Сообщения текущего пользователя выравниваются справа.
 * Сравниваем sender_max_user_id, чтобы разные админы не видели чужие сообщения как «Вы».
 *
 * @param {{ sender_max_user_id?: number, author_type?: string }} message
 * @returns {boolean}
 */
function isOwnMessage(message) {
    if (maxUserId.value != null && message.sender_max_user_id != null) {
        return Number(message.sender_max_user_id) === Number(maxUserId.value);
    }

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

watch(
    () => messages.value.length,
    async (length, previousLength) => {
        if (length > 0 && length !== previousLength) {
            await scrollToBottom();
        }
    },
);

async function handleSend() {
    const sent = await sendMessage();

    if (sent) {
        await scrollToBottom();
    }
}

function handleKeydown(event) {
    if (event.key === 'Enter' && !event.shiftKey) {
        event.preventDefault();
        handleSend();
    }
}

async function handleRetryLoad() {
    await loadMessages();
    await scrollToBottom();
}
</script>

<template>
    <div
        class="flex min-h-0 flex-col overflow-hidden rounded-2xl border bg-gray-50"
        :class="active ? 'border-max-primary/50' : 'border-gray-100'"
        @pointerdown="emitActivate"
        @focusin="emitActivate"
    >
        <div
            class="shrink-0 border-b border-gray-100 bg-white"
            :class="compact ? 'px-3 py-1.5' : 'px-4 py-3'"
        >
            <h2 class="font-semibold text-gray-900" :class="compact ? 'text-xs' : 'text-sm'">Чат по заказу</h2>
            <p v-if="!compact" class="text-xs text-max-muted">Уточнения и вопросы по заявке</p>
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
                    @click="handleRetryLoad"
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

        <div
            class="shrink-0 border-t border-gray-200 bg-white px-3"
            :class="composerFooterPadClass"
        >
            <div
                v-if="sendError"
                class="mb-2 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-xs text-red-700"
            >
                {{ sendError }}
            </div>

            <div class="flex items-end gap-2">
                <textarea
                    v-model="body"
                    :rows="compact ? 1 : 2"
                    :maxlength="ORDER_CHAT_MAX_BODY_LENGTH"
                    :disabled="loading || !!loadError || sending"
                    placeholder="Ваше сообщение…"
                    class="flex-1 resize-none rounded-xl border border-gray-200 bg-gray-50 px-3 text-sm text-gray-900 placeholder:text-max-muted focus:border-max-primary focus:bg-white focus:outline-none focus:ring-1 focus:ring-max-primary disabled:opacity-50"
                    :class="compact ? 'min-h-[36px] py-2' : 'min-h-[44px] py-2.5'"
                    @keydown="handleKeydown"
                />
                <button
                    type="button"
                    class="flex shrink-0 items-center justify-center rounded-xl bg-max-primary text-white transition hover:bg-max-primary-hover disabled:opacity-50"
                    :class="compact ? 'h-9 w-9' : 'h-11 w-11'"
                    :disabled="loading || !!loadError || sending || body.trim() === ''"
                    aria-label="Отправить"
                    @click="handleSend"
                >
                    <svg
                        v-if="!sending"
                        class="h-5 w-5 rotate-90"
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
