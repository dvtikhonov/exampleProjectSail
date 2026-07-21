<script setup>
/**
 * Выбор потребителя для ручного заказа: выпадающий список с поиском по ФИО.
 */
import { computed, ref, watch } from 'vue';
import AppSearchSelect from '../../components/AppSearchSelect.vue';
import { formatCustomerName } from '../../utils/formatCustomerName';

const props = defineProps({
    users: {
        type: Array,
        default: () => [],
    },
    loading: {
        type: Boolean,
        default: false,
    },
    error: {
        type: String,
        default: '',
    },
    query: {
        type: String,
        default: '',
    },
});

const emit = defineEmits(['search', 'select-user', 'refresh']);

const selectedUserId = ref('');

const userOptions = computed(() => props.users.map((user) => ({
    value: String(user.max_user_id),
    label: formatCustomerName(user) || 'Потребитель',
    description: userSecondaryLabel(user),
})));

const emptyText = computed(() => (
    props.query.trim() !== '' ? 'Никого не найдено' : 'Нет потребителей'
));

watch(
    () => props.users,
    (users) => {
        if (selectedUserId.value === '') {
            return;
        }

        const stillExists = users.some(
            (user) => String(user.max_user_id) === selectedUserId.value,
        );

        if (!stillExists) {
            selectedUserId.value = '';
        }
    },
);

/**
 * @param {{ username?: string|null, delivery_address?: string|null }} user
 * @returns {string}
 */
function userSecondaryLabel(user) {
    const parts = [];

    if (typeof user?.username === 'string' && user.username.trim() !== '') {
        parts.push(`@${user.username.trim().replace(/^@/, '')}`);
    }

    if (typeof user?.delivery_address === 'string' && user.delivery_address.trim() !== '') {
        parts.push(user.delivery_address.trim());
    }

    return parts.join(' · ');
}

/**
 * @param {string} value
 */
function onSelectUserId(value) {
    selectedUserId.value = value;

    const user = props.users.find((item) => String(item.max_user_id) === String(value));

    if (user) {
        emit('select-user', user);
    }
}

/**
 * @param {string} value
 */
function onSearch(value) {
    emit('search', value);
}
</script>

<template>
    <div class="flex h-full min-h-0 flex-col">
        <header class="sticky top-0 z-10 border-b border-gray-200 bg-white safe-area-top">
            <div class="px-4 py-3">
                <h1 class="text-lg font-semibold text-gray-900">Ручные заказы</h1>
                <p class="text-sm text-max-muted">Выберите потребителя по ФИО</p>
            </div>
        </header>

        <main class="min-h-0 flex-1 overflow-y-auto px-4 py-4">
            <div
                v-if="error"
                class="mb-3 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"
            >
                {{ error }}
                <button
                    type="button"
                    class="mt-2 block text-sm font-medium text-red-800 underline"
                    @click="emit('refresh')"
                >
                    Повторить
                </button>
            </div>

            <div class="rounded-2xl border border-gray-100 bg-white p-4 shadow-sm">
                <label
                    class="mb-2 block text-sm font-medium text-gray-900"
                    for="manual-order-consumer-select"
                >
                    Потребитель
                </label>
                <AppSearchSelect
                    id="manual-order-consumer-select"
                    :model-value="selectedUserId"
                    :options="userOptions"
                    :query="query"
                    :loading="loading"
                    :empty-text="emptyText"
                    placeholder="Выберите потребителя"
                    search-placeholder="ФИО, username или адрес"
                    @update:model-value="onSelectUserId"
                    @search="onSearch"
                />
            </div>
        </main>
    </div>
</template>
