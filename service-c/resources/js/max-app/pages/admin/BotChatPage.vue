<script setup>
/**
 * Раздел «Чат бота»: выбор пользователя MAX + лента переписки + отправка ответа.
 */
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';
import { extractErrorMessage, fetchBotDmUsers } from '../../api';
import AppSearchSelect from '../../components/AppSearchSelect.vue';
import OrderChatMessage from '../../components/OrderChatMessage.vue';
import { useAuth } from '../../composables/useAuth';
import { useBotDmChat } from '../../composables/useBotDmChat';
import { BOT_DM_CHAT_MAX_BODY_LENGTH } from '../../constants/botDmChat';
import { formatCustomerName } from '../../utils/formatCustomerName';

/** Задержка debounce поиска пользователей (мс) */
const SEARCH_DEBOUNCE_MS = 300;

const { maxUserId: currentAdminMaxUserId } = useAuth();

const users = ref([]);
const usersLoading = ref(false);
const usersError = ref('');
const usersQuery = ref('');
/** @type {import('vue').Ref<object|null>} */
const selectedUser = ref(null);
const selectedUserId = ref('');

/** @type {ReturnType<typeof setTimeout>|null} */
let searchDebounceTimer = null;

const selectedMaxUserId = computed(() => {
    const id = Number(selectedUserId.value);

    return Number.isFinite(id) && id > 0 ? id : null;
});

const {
    messages,
    loading,
    loadError,
    sending,
    sendError,
    body,
    loadMessages,
    sendMessage,
} = useBotDmChat({
    maxUserId: selectedMaxUserId,
});

const messagesContainer = ref(null);

const userOptions = computed(() => {
    const byId = new Map();

    for (const user of users.value) {
        byId.set(String(user.max_user_id), user);
    }

    if (selectedUser.value?.max_user_id != null) {
        byId.set(String(selectedUser.value.max_user_id), selectedUser.value);
    }

    return [...byId.values()].map((user) => ({
        value: String(user.max_user_id),
        label: formatCustomerName(user) || `ID ${user.max_user_id}`,
        description: userSecondaryLabel(user),
    }));
});

const emptyText = computed(() => (
    usersQuery.value.trim() !== '' ? 'Никого не найдено' : 'Нет пользователей'
));

const hasSelectedUser = computed(() => selectedMaxUserId.value !== null);

const selectedUserTitle = computed(() => {
    if (!selectedUser.value) {
        return '';
    }

    return formatCustomerName(selectedUser.value) || `ID ${selectedUser.value.max_user_id}`;
});

/**
 * @param {{ username?: string|null, max_user_id?: number }} user
 * @returns {string}
 */
function userSecondaryLabel(user) {
    const parts = [];

    if (typeof user?.username === 'string' && user.username.trim() !== '') {
        parts.push(`@${user.username.trim().replace(/^@/, '')}`);
    }

    if (user?.max_user_id != null) {
        parts.push(`id ${user.max_user_id}`);
    }

    return parts.join(' · ');
}

/**
 * @param {{ q?: string }} [options]
 */
async function loadUsers({ q } = {}) {
    const query = typeof q === 'string' ? q : usersQuery.value;

    usersLoading.value = true;
    usersError.value = '';

    try {
        users.value = await fetchBotDmUsers({ q: query });
    } catch (error) {
        usersError.value = extractErrorMessage(error);
        users.value = [];
    } finally {
        usersLoading.value = false;
    }
}

/**
 * @param {string} query
 */
function onSearch(query) {
    usersQuery.value = query;

    if (searchDebounceTimer !== null) {
        clearTimeout(searchDebounceTimer);
    }

    searchDebounceTimer = setTimeout(() => {
        searchDebounceTimer = null;
        loadUsers({ q: usersQuery.value });
    }, SEARCH_DEBOUNCE_MS);
}

/**
 * @param {string} value
 */
function onSelectUserId(value) {
    selectedUserId.value = value;

    if (value === '' || value == null) {
        selectedUser.value = null;

        return;
    }

    const fromList = users.value.find((item) => String(item.max_user_id) === String(value));

    if (fromList) {
        selectedUser.value = fromList;

        return;
    }

    if (
        selectedUser.value
        && String(selectedUser.value.max_user_id) === String(value)
    ) {
        return;
    }

    selectedUser.value = null;
}

/**
 * Подпись «Вы» — только для сообщений текущего админа.
 *
 * @param {{ sender_max_user_id?: number, author_type?: string }} message
 * @returns {boolean}
 */
function isOwnMessage(message) {
    if (
        currentAdminMaxUserId.value != null
        && message.sender_max_user_id != null
    ) {
        return Number(message.sender_max_user_id) === Number(currentAdminMaxUserId.value);
    }

    return message.author_type === 'admin';
}

/**
 * Выравнивание: все сообщения админа справа, клиента слева.
 *
 * @param {{ author_type?: string }} message
 * @returns {boolean}
 */
function isAdminAligned(message) {
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

/**
 * @param {KeyboardEvent} event
 */
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

onMounted(() => {
    loadUsers({ q: '' });
});

onUnmounted(() => {
    if (searchDebounceTimer !== null) {
        clearTimeout(searchDebounceTimer);
        searchDebounceTimer = null;
    }
});
</script>

<template>
    <div class="flex h-full min-h-0 flex-col overflow-hidden">
        <header class="shrink-0 border-b border-gray-200 bg-white">
            <div class="px-4 py-3">
                <h1 class="text-lg font-semibold text-gray-900">Чат бота</h1>
                <p class="text-sm text-max-muted">
                    Выберите пользователя и ответьте в личные сообщения MAX
                </p>
            </div>

            <div class="px-4 pb-3">
                <div
                    v-if="usersError"
                    class="mb-3 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"
                >
                    {{ usersError }}
                    <button
                        type="button"
                        class="mt-2 block text-sm font-medium text-red-800 underline"
                        @click="loadUsers()"
                    >
                        Повторить
                    </button>
                </div>

                <div class="rounded-2xl border border-gray-100 bg-white p-4 shadow-sm">
                    <label
                        class="mb-2 block text-sm font-medium text-gray-900"
                        for="bot-dm-user-select"
                    >
                        Пользователь
                    </label>
                    <AppSearchSelect
                        id="bot-dm-user-select"
                        :model-value="selectedUserId"
                        :options="userOptions"
                        :query="usersQuery"
                        :loading="usersLoading"
                        :empty-text="emptyText"
                        placeholder="Выберите пользователя"
                        search-placeholder="ФИО, username или id"
                        @update:model-value="onSelectUserId"
                        @search="onSearch"
                    />
                </div>
            </div>
        </header>

        <div
            v-if="!hasSelectedUser"
            class="flex min-h-0 flex-1 items-center justify-center px-4"
        >
            <p class="py-8 text-center text-sm text-max-muted">
                Выберите пользователя, чтобы открыть переписку
            </p>
        </div>

        <div
            v-else
            class="flex min-h-0 flex-1 flex-col overflow-hidden bg-gray-50"
        >
            <div class="shrink-0 border-b border-gray-100 bg-white px-4 py-2">
                <h2 class="truncate text-sm font-semibold text-gray-900">
                    {{ selectedUserTitle }}
                </h2>
                <p class="text-xs text-max-muted">
                    Личные сообщения через бота
                </p>
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
                    Сообщений пока нет. Напишите пользователю первым.
                </p>

                <OrderChatMessage
                    v-for="message in messages"
                    :key="message.id"
                    :message="message"
                    :is-own="isOwnMessage(message)"
                    :align-end="isAdminAligned(message)"
                    perspective="admin"
                />
            </div>

            <div
                class="shrink-0 border-t border-gray-200 bg-white px-3 pt-3 pb-[calc(0.75rem+env(safe-area-inset-bottom,0px))]"
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
                        rows="2"
                        :maxlength="BOT_DM_CHAT_MAX_BODY_LENGTH"
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
    </div>
</template>
