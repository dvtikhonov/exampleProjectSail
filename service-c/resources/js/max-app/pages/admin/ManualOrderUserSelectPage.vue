<script setup>
/**
 * Раздел «Ручные заказы»: сначала выбор потребителя, затем «Оформить» или «Просмотр».
 */
import { computed, nextTick, onActivated, ref, watch } from 'vue';
import AppSearchSelect from '../../components/AppSearchSelect.vue';
import AppSelect from '../../components/AppSelect.vue';
import OrderStatusBadge from '../../components/OrderStatusBadge.vue';
import { MANUAL_ORDER_TABS } from '../../composables/useManualOrder';
import { useScrollViewport } from '../../composables/useScrollViewport';
import { formatCustomerName } from '../../utils/formatCustomerName';

/** Опции фильтра статуса в просмотре ручных заказов */
const STATUS_FILTER_OPTIONS = [
    { value: '', label: 'Все статусы' },
    { value: 'pending_review', label: 'На проверке' },
    { value: 'awaiting_composition', label: 'Ожидает состав' },
    { value: 'confirmed', label: 'Выполнен' },
    { value: 'rejected', label: 'Отклонён' },
];

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
    selectedConsumer: {
        type: Object,
        default: null,
    },
    selectedConsumerId: {
        type: [String, Number],
        default: '',
    },
    activeTab: {
        type: String,
        default: MANUAL_ORDER_TABS.create,
    },
    orders: {
        type: Array,
        default: () => [],
    },
    ordersLoading: {
        type: Boolean,
        default: false,
    },
    ordersError: {
        type: String,
        default: '',
    },
    ordersDateFrom: {
        type: String,
        default: '',
    },
    ordersDateTo: {
        type: String,
        default: '',
    },
    ordersStatus: {
        type: String,
        default: '',
    },
    ordersTotal: {
        type: Number,
        default: 0,
    },
    ordersTotalAmount: {
        type: String,
        default: '0.00',
    },
});

const emit = defineEmits([
    'search',
    'select-consumer',
    'start-order',
    'refresh',
    'change-tab',
    'orders-date-from',
    'orders-date-to',
    'orders-status',
    'orders-refresh',
    'select-order',
]);

const selectedUserId = ref(
    props.selectedConsumerId !== '' && props.selectedConsumerId != null
        ? String(props.selectedConsumerId)
        : '',
);

const listViewportRef = ref(null);
const {
    refreshViewport,
    readScrollTop,
    applyScrollTop,
} = useScrollViewport(listViewportRef, { enableTouchScroll: true });

/** @type {number} */
let savedScrollTop = 0;

const isCreateTab = computed(() => props.activeTab === MANUAL_ORDER_TABS.create);
const isListTab = computed(() => props.activeTab === MANUAL_ORDER_TABS.list);
const hasConsumer = computed(() => selectedUserId.value !== '');

const userOptions = computed(() => {
    const byId = new Map();

    for (const user of props.users) {
        byId.set(String(user.max_user_id), user);
    }

    if (props.selectedConsumer?.max_user_id != null) {
        byId.set(String(props.selectedConsumer.max_user_id), props.selectedConsumer);
    }

    return [...byId.values()].map((user) => ({
        value: String(user.max_user_id),
        label: formatCustomerName(user) || 'Потребитель',
        description: userSecondaryLabel(user),
    }));
});

const emptyText = computed(() => (
    props.query.trim() !== '' ? 'Никого не найдено' : 'Нет потребителей'
));

const ordersEmptyText = computed(() => {
    const hasFilters = props.ordersDateFrom !== ''
        || props.ordersDateTo !== ''
        || props.ordersStatus !== '';

    return hasFilters ? 'Заказы не найдены по выбранным фильтрам' : 'Нет ручных заказов у потребителя';
});

watch(
    () => props.selectedConsumerId,
    (id) => {
        selectedUserId.value = id !== '' && id != null ? String(id) : '';
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

    if (value === '' || value == null) {
        emit('select-consumer', null);

        return;
    }

    const fromList = props.users.find((item) => String(item.max_user_id) === String(value));

    if (fromList) {
        emit('select-consumer', fromList);

        return;
    }

    if (
        props.selectedConsumer
        && String(props.selectedConsumer.max_user_id) === String(value)
    ) {
        emit('select-consumer', props.selectedConsumer);

        return;
    }

    emit('select-consumer', null);
}

/**
 * @param {string} value
 */
function onSearch(value) {
    emit('search', value);
}

function onStartOrder() {
    if (!hasConsumer.value) {
        return;
    }

    emit('start-order');
}

/**
 * @param {'create'|'list'} tab
 */
function onChangeTab(tab) {
    if (!hasConsumer.value) {
        return;
    }

    if (tab === MANUAL_ORDER_TABS.create) {
        onStartOrder();

        return;
    }

    emit('change-tab', tab);
}

/**
 * @param {Event} event
 */
function onDateFromChange(event) {
    emit('orders-date-from', event.target.value);
}

/**
 * @param {Event} event
 */
function onDateToChange(event) {
    emit('orders-date-to', event.target.value);
}

/**
 * @param {string} value
 */
function onStatusChange(value) {
    emit('orders-status', value);
}

/**
 * @param {{ first_name?: string|null, last_name?: string|null, username?: string|null, max_user_id: number }} customer
 * @returns {string}
 */
function formatOrderCustomer(customer) {
    return formatCustomerName(customer) || `ID ${customer?.max_user_id ?? '—'}`;
}

/**
 * @param {string} iso
 * @returns {string}
 */
function formatDate(iso) {
    try {
        return new Intl.DateTimeFormat('ru-RU', {
            day: 'numeric',
            month: 'short',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        }).format(new Date(iso));
    } catch {
        return iso;
    }
}

function captureScrollPosition() {
    savedScrollTop = readScrollTop();
}

function restoreScrollPosition() {
    const apply = () => {
        if (savedScrollTop > 0) {
            applyScrollTop(savedScrollTop);
        }
    };

    nextTick(() => {
        apply();
        requestAnimationFrame(() => {
            apply();
        });
    });
}

/**
 * @param {{ id?: number|string }} order
 */
function onSelectOrder(order) {
    captureScrollPosition();
    emit('select-order', order);
}

onActivated(() => {
    refreshViewport();
    restoreScrollPosition();
});

watch(
    () => [props.ordersLoading, props.orders.length, props.activeTab],
    () => {
        refreshViewport();
    },
);

watch(
    () => props.ordersLoading,
    (loading, prevLoading) => {
        if (prevLoading && !loading && savedScrollTop > 0) {
            restoreScrollPosition();
        }
    },
);
</script>

<template>
    <div class="flex h-full min-h-0 flex-col overflow-hidden">
        <header class="shrink-0 border-b border-gray-200 bg-white">
            <div class="px-4 py-3">
                <h1 class="text-lg font-semibold text-gray-900">Ручные заказы</h1>
                <p class="text-sm text-max-muted">
                    Сначала выберите потребителя, затем оформите заказ или откройте просмотр
                </p>
            </div>

            <div class="px-4 pb-3">
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
            </div>

            <div class="flex gap-1 px-4 pb-3">
                <button
                    type="button"
                    class="flex-1 rounded-xl px-3 py-2 text-sm font-medium transition"
                    :disabled="!hasConsumer"
                    :class="[
                        !hasConsumer
                            ? 'cursor-not-allowed bg-gray-100 text-gray-400'
                            : isCreateTab
                                ? 'bg-max-primary text-white'
                                : 'bg-gray-100 text-gray-700',
                    ]"
                    @click="onChangeTab(MANUAL_ORDER_TABS.create)"
                >
                    Оформить
                </button>
                <button
                    type="button"
                    class="flex-1 rounded-xl px-3 py-2 text-sm font-medium transition"
                    :disabled="!hasConsumer"
                    :class="[
                        !hasConsumer
                            ? 'cursor-not-allowed bg-gray-100 text-gray-400'
                            : isListTab
                                ? 'bg-max-primary text-white'
                                : 'bg-gray-100 text-gray-700',
                    ]"
                    @click="onChangeTab(MANUAL_ORDER_TABS.list)"
                >
                    Просмотр
                </button>
            </div>
        </header>

        <main
            ref="listViewportRef"
            class="max-app-scroll-viewport px-4 py-4"
            tabindex="0"
            role="region"
            aria-label="Ручные заказы"
        >
            <template v-if="!hasConsumer">
                <p class="py-12 text-center text-sm text-max-muted">
                    Выберите потребителя, чтобы оформить заказ или посмотреть его ручные заказы
                </p>
            </template>

            <template v-else-if="isCreateTab">
                <p class="py-12 text-center text-sm text-max-muted">
                    Нажмите «Оформить», чтобы создать заказ для выбранного потребителя
                </p>
            </template>

            <template v-else>
                <div class="mb-4 space-y-3 rounded-2xl border border-gray-100 bg-white p-4 shadow-sm">
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label
                                class="mb-1.5 block text-sm font-medium text-gray-900"
                                for="manual-orders-date-from"
                            >
                                С даты
                            </label>
                            <input
                                id="manual-orders-date-from"
                                type="date"
                                class="w-full rounded-xl border border-gray-200 bg-white px-3 py-2.5 text-sm text-gray-900 outline-none focus:border-max-primary focus:ring-2 focus:ring-max-primary/20"
                                :value="ordersDateFrom"
                                @change="onDateFromChange"
                            >
                        </div>
                        <div>
                            <label
                                class="mb-1.5 block text-sm font-medium text-gray-900"
                                for="manual-orders-date-to"
                            >
                                По дату
                            </label>
                            <input
                                id="manual-orders-date-to"
                                type="date"
                                class="w-full rounded-xl border border-gray-200 bg-white px-3 py-2.5 text-sm text-gray-900 outline-none focus:border-max-primary focus:ring-2 focus:ring-max-primary/20"
                                :value="ordersDateTo"
                                @change="onDateToChange"
                            >
                        </div>
                    </div>
                    <div>
                        <label
                            class="mb-1.5 block text-sm font-medium text-gray-900"
                            for="manual-orders-status"
                        >
                            Статус
                        </label>
                        <AppSelect
                            id="manual-orders-status"
                            :model-value="ordersStatus"
                            :options="STATUS_FILTER_OPTIONS"
                            placeholder="Все статусы"
                            @update:model-value="onStatusChange"
                        />
                    </div>
                </div>

                <div
                    v-if="ordersError"
                    class="mb-3 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"
                >
                    {{ ordersError }}
                    <button
                        type="button"
                        class="mt-2 block text-sm font-medium text-red-800 underline"
                        @click="emit('orders-refresh')"
                    >
                        Повторить
                    </button>
                </div>

                <div
                    v-else-if="ordersLoading && orders.length === 0"
                    class="flex items-center justify-center py-16"
                >
                    <div class="h-8 w-8 animate-spin rounded-full border-4 border-max-primary border-t-transparent" />
                </div>

                <div
                    v-else-if="orders.length === 0"
                    class="py-12 text-center text-sm text-max-muted"
                >
                    {{ ordersEmptyText }}
                </div>

                <div v-else>
                    <p class="mb-3 flex items-center justify-between gap-3 text-xs text-max-muted">
                        <span>
                            Найдено: {{ ordersTotal }}
                            <span v-if="ordersLoading"> · обновление…</span>
                        </span>
                        <span class="shrink-0 text-right">
                            На сумму: {{ ordersTotalAmount }} ₽
                        </span>
                    </p>

                    <ul class="space-y-3">
                        <li
                            v-for="order in orders"
                            :key="order.id"
                        >
                            <button
                                type="button"
                                class="w-full rounded-2xl border border-gray-100 bg-white p-4 text-left shadow-sm transition active:scale-[0.98] hover:border-max-primary/30 hover:shadow-md"
                                @click="onSelectOrder(order)"
                            >
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0 flex-1">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <span class="font-semibold text-gray-900">№{{ order.id }}</span>
                                            <OrderStatusBadge :order="order" />
                                            <span class="text-xs text-max-muted">
                                                {{ formatDate(order.created_at) }}
                                            </span>
                                        </div>
                                        <p class="mt-1 truncate text-sm text-gray-700">
                                            {{ order.restaurant_name }}
                                        </p>
                                        <p class="mt-0.5 truncate text-sm text-max-muted">
                                            {{ formatOrderCustomer(order.customer) }}
                                        </p>
                                        <p
                                            v-if="order.delivery_address"
                                            class="mt-1 line-clamp-2 text-sm text-gray-600"
                                        >
                                            {{ order.delivery_address }}
                                        </p>
                                    </div>
                                    <div class="shrink-0 text-right">
                                        <p class="font-semibold text-gray-900">
                                            {{ order.total }} ₽
                                        </p>
                                        <svg
                                            class="ml-auto mt-2 h-5 w-5 text-gray-300"
                                            fill="none"
                                            viewBox="0 0 24 24"
                                            stroke="currentColor"
                                            stroke-width="2"
                                        >
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                                        </svg>
                                    </div>
                                </div>
                            </button>
                        </li>
                    </ul>
                </div>
            </template>
        </main>
    </div>
</template>
