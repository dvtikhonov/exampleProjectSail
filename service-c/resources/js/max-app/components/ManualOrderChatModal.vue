<script setup>
/**
 * Модалка просмотра истории чата ручного заказа (только чтение).
 */
import { computed, ref, watch } from 'vue';
import { extractErrorMessage, fetchOrderMessages } from '../api';
import { useAuth } from '../composables/useAuth';
import OrderChatMessage from './OrderChatMessage.vue';

const props = defineProps({
    open: {
        type: Boolean,
        default: false,
    },
    orderId: {
        type: Number,
        default: null,
    },
});

const emit = defineEmits(['close']);

const { maxUserId } = useAuth();

const messages = ref([]);
const loading = ref(false);
const loadError = ref('');

const titleId = computed(() => `manual-order-chat-${props.orderId ?? 'none'}`);

/**
 * @param {{ sender_max_user_id?: number, author_type?: string }} message
 * @returns {boolean}
 */
function isOwnMessage(message) {
    if (maxUserId.value != null && message.sender_max_user_id != null) {
        return Number(message.sender_max_user_id) === Number(maxUserId.value);
    }

    return message.author_type === 'admin';
}

async function loadMessages() {
    if (props.orderId == null) {
        return;
    }

    loading.value = true;
    loadError.value = '';

    try {
        messages.value = await fetchOrderMessages(props.orderId, { limit: 100 });
    } catch (error) {
        loadError.value = extractErrorMessage(error);
        messages.value = [];
    } finally {
        loading.value = false;
    }
}

watch(
    () => [props.open, props.orderId],
    async ([isOpen]) => {
        if (!isOpen) {
            return;
        }

        messages.value = [];
        await loadMessages();
    },
);
</script>

<template>
    <Teleport to="body">
        <div
            v-if="open"
            class="fixed inset-0 z-50 flex items-end justify-center bg-black/40 sm:items-center"
            @click.self="emit('close')"
        >
            <div
                class="flex max-h-[85vh] w-full max-w-lg flex-col overflow-hidden rounded-t-2xl bg-white shadow-xl sm:rounded-2xl"
                role="dialog"
                aria-modal="true"
                :aria-labelledby="titleId"
            >
                <div class="flex shrink-0 items-center justify-between gap-3 border-b border-gray-100 px-4 py-3">
                    <div class="min-w-0">
                        <h2
                            :id="titleId"
                            class="text-lg font-semibold text-gray-900"
                        >
                            Чат по заказу
                        </h2>
                        <p
                            v-if="orderId != null"
                            class="text-sm text-max-muted"
                        >
                            Заказ №{{ orderId }}
                        </p>
                    </div>
                    <button
                        type="button"
                        class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-gray-500 transition hover:bg-gray-100 hover:text-gray-800"
                        aria-label="Закрыть"
                        @click="emit('close')"
                    >
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <div class="min-h-0 flex-1 space-y-3 overflow-y-auto bg-gray-50 px-3 py-4">
                    <div
                        v-if="loading"
                        class="flex items-center justify-center py-12"
                    >
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
                        Сообщений нет
                    </p>

                    <OrderChatMessage
                        v-for="message in messages"
                        :key="message.id"
                        :message="message"
                        :is-own="isOwnMessage(message)"
                        perspective="admin"
                    />
                </div>

                <div class="shrink-0 border-t border-gray-100 px-4 py-3 safe-area-bottom">
                    <button
                        type="button"
                        class="w-full rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm font-medium text-gray-800 transition hover:bg-gray-50"
                        @click="emit('close')"
                    >
                        Закрыть
                    </button>
                </div>
            </div>
        </div>
    </Teleport>
</template>
