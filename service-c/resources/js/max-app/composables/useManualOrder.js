/**
 * Ручные заказы (роль max_manager): выбор потребителя, просмотр списка и контекст targetMaxUserId.
 * Корзина/submit идут через admin manual-orders API при активном target.
 */
import { computed, onScopeDispose, ref } from 'vue';
import {
    extractErrorMessage,
    fetchManualOrder,
    fetchManualOrderUsers,
    fetchManualOrders,
} from '../api/foodClient';
import { formatCustomerFio } from '../utils/formatCustomerName';

/** Задержка debounce поиска пользователей (мс) */
const SEARCH_DEBOUNCE_MS = 300;

/** Вкладки раздела «Ручные заказы» */
export const MANUAL_ORDER_TABS = {
    create: 'create',
    list: 'list',
};

/**
 * Подпись потребителя с ФИО для шапки ручного заказа.
 *
 * @param {object|null|undefined} user — элемент из GET manual-orders/users
 * @returns {string}
 */
export function formatManualOrderCustomerLabel(user) {
    if (!user || typeof user !== 'object') {
        return '';
    }

    const fio = formatCustomerFio(user);
    const name = fio !== '' ? fio : 'Потребитель';
    const username = typeof user.username === 'string' && user.username.trim() !== ''
        ? user.username.trim().replace(/^@/, '')
        : '';

    return username !== '' ? `${name} (@${username})` : name;
}

/** Пустая meta списка ручных заказов */
function emptyOrdersMeta() {
    return {
        current_page: 1,
        per_page: 30,
        total: 0,
        last_page: 1,
        total_amount: '0.00',
    };
}

/**
 * @returns {object} Состояние и методы раздела «Ручные заказы»
 */
export function useManualOrder() {
    const targetMaxUserId = ref(null);
    /** @type {import('vue').Ref<object|null>} */
    const targetUser = ref(null);
    /** Выбранный в UI потребитель до оформления / для фильтра просмотра */
    /** @type {import('vue').Ref<object|null>} */
    const selectedConsumer = ref(null);
    const users = ref([]);
    const usersLoading = ref(false);
    const usersError = ref('');
    const usersQuery = ref('');
    const activeTab = ref(MANUAL_ORDER_TABS.create);

    const orders = ref([]);
    const ordersMeta = ref(emptyOrdersMeta());
    const ordersLoading = ref(false);
    const ordersError = ref('');
    const ordersDateFrom = ref('');
    const ordersDateTo = ref('');
    /** Фильтр статуса: '' = все */
    const ordersStatus = ref('');

    /** @type {import('vue').Ref<object|null>} */
    const selectedOrderDetail = ref(null);
    const orderDetailLoading = ref(false);
    const orderDetailError = ref('');

    /** @type {ReturnType<typeof setTimeout>|null} */
    let searchDebounceTimer = null;

    const isOrdering = computed(() => targetMaxUserId.value !== null);

    const hasSelectedConsumer = computed(() => selectedConsumer.value !== null);

    const selectedConsumerMaxUserId = computed(() => {
        const rawId = selectedConsumer.value?.max_user_id;
        const id = typeof rawId === 'number' ? rawId : Number(rawId);

        return Number.isFinite(id) && id > 0 ? id : null;
    });

    const customerLabel = computed(() => formatManualOrderCustomerLabel(targetUser.value));

    const isOrderDetailOpen = computed(() => selectedOrderDetail.value !== null || orderDetailLoading.value);

    /**
     * @param {{ q?: string }} [options]
     */
    async function loadUsers({ q } = {}) {
        const query = typeof q === 'string' ? q : usersQuery.value;

        usersLoading.value = true;
        usersError.value = '';

        try {
            users.value = await fetchManualOrderUsers({ q: query });
        } catch (error) {
            usersError.value = extractErrorMessage(error);
            users.value = [];
        } finally {
            usersLoading.value = false;
        }
    }

    /**
     * @param {{ dateFrom?: string, dateTo?: string, status?: string, maxUserId?: number|null }} [options]
     */
    async function loadOrders({ dateFrom, dateTo, status, maxUserId } = {}) {
        const from = typeof dateFrom === 'string' ? dateFrom : ordersDateFrom.value;
        const to = typeof dateTo === 'string' ? dateTo : ordersDateTo.value;
        const statusFilter = typeof status === 'string' ? status : ordersStatus.value;
        const consumerId = maxUserId !== undefined
            ? maxUserId
            : selectedConsumerMaxUserId.value;

        if (consumerId === null) {
            orders.value = [];
            ordersMeta.value = emptyOrdersMeta();
            ordersError.value = '';
            ordersLoading.value = false;

            return;
        }

        ordersLoading.value = true;
        ordersError.value = '';

        try {
            const result = await fetchManualOrders({
                maxUserId: consumerId,
                dateFrom: from || null,
                dateTo: to || null,
                status: statusFilter || null,
            });
            orders.value = result.orders;
            ordersMeta.value = {
                ...emptyOrdersMeta(),
                ...result.meta,
            };
        } catch (error) {
            ordersError.value = extractErrorMessage(error);
            orders.value = [];
            ordersMeta.value = emptyOrdersMeta();
        } finally {
            ordersLoading.value = false;
        }
    }

    /**
     * @param {string} query
     */
    function handleUsersSearchInput(query) {
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
     * @param {string} value — Y-m-d или пустая строка
     */
    function handleOrdersDateFromChange(value) {
        ordersDateFrom.value = value;
        loadOrders();
    }

    /**
     * @param {string} value — Y-m-d или пустая строка
     */
    function handleOrdersDateToChange(value) {
        ordersDateTo.value = value;
        loadOrders();
    }

    /**
     * @param {string} value — код статуса или ''
     */
    function handleOrdersStatusChange(value) {
        ordersStatus.value = typeof value === 'string' ? value : '';
        loadOrders();
    }

    /**
     * Выбор потребителя в UI (ещё не оформление).
     *
     * @param {object|null} user
     */
    function setSelectedConsumer(user) {
        closeOrderDetail();

        if (!user || typeof user !== 'object') {
            selectedConsumer.value = null;

            if (activeTab.value === MANUAL_ORDER_TABS.list) {
                orders.value = [];
                ordersMeta.value = emptyOrdersMeta();
            }

            return;
        }

        const rawId = user.max_user_id;
        const id = typeof rawId === 'number' ? rawId : Number(rawId);

        if (!Number.isFinite(id) || id <= 0) {
            return;
        }

        selectedConsumer.value = user;

        if (activeTab.value === MANUAL_ORDER_TABS.list) {
            loadOrders({ maxUserId: id });
        }
    }

    /**
     * @param {'create'|'list'} tab
     */
    function setActiveTab(tab) {
        if (tab !== MANUAL_ORDER_TABS.create && tab !== MANUAL_ORDER_TABS.list) {
            return;
        }

        if (tab === MANUAL_ORDER_TABS.list && selectedConsumerMaxUserId.value === null) {
            return;
        }

        closeOrderDetail();
        activeTab.value = tab;

        if (tab === MANUAL_ORDER_TABS.list) {
            loadOrders();
        }
    }

    /**
     * @param {{ id?: number|string }} order
     */
    async function openOrderDetail(order) {
        const rawId = order?.id;
        const orderId = typeof rawId === 'number' ? rawId : Number(rawId);

        if (!Number.isFinite(orderId) || orderId <= 0) {
            return;
        }

        selectedOrderDetail.value = null;
        orderDetailError.value = '';
        orderDetailLoading.value = true;

        try {
            selectedOrderDetail.value = await fetchManualOrder(orderId);
        } catch (error) {
            orderDetailError.value = extractErrorMessage(error);
            selectedOrderDetail.value = { id: orderId };
        } finally {
            orderDetailLoading.value = false;
        }
    }

    function closeOrderDetail() {
        selectedOrderDetail.value = null;
        orderDetailLoading.value = false;
        orderDetailError.value = '';
    }

    /**
     * @param {{ max_user_id?: number|string }} user
     */
    function selectUser(user) {
        const rawId = user?.max_user_id;
        const id = typeof rawId === 'number' ? rawId : Number(rawId);

        if (!Number.isFinite(id) || id <= 0) {
            return;
        }

        selectedConsumer.value = user;
        targetMaxUserId.value = id;
        targetUser.value = user;
    }

    function clearTargetUser() {
        targetMaxUserId.value = null;
        targetUser.value = null;
    }

    /** Сброс выбора и загрузка списка при входе в раздел */
    function initManualOrderSession() {
        clearTargetUser();
        selectedConsumer.value = null;
        usersQuery.value = '';
        ordersDateFrom.value = '';
        ordersDateTo.value = '';
        ordersStatus.value = '';
        orders.value = [];
        ordersMeta.value = emptyOrdersMeta();
        ordersError.value = '';
        closeOrderDetail();
        activeTab.value = MANUAL_ORDER_TABS.create;
        loadUsers({ q: '' });
    }

    /**
     * max_user_id клиента для manual cart API или null вне режима оформления.
     *
     * @returns {number|null}
     */
    function getTargetMaxUserId() {
        return targetMaxUserId.value;
    }

    onScopeDispose(() => {
        if (searchDebounceTimer !== null) {
            clearTimeout(searchDebounceTimer);
            searchDebounceTimer = null;
        }
    });

    return {
        targetMaxUserId,
        targetUser,
        selectedConsumer,
        selectedConsumerMaxUserId,
        hasSelectedConsumer,
        customerLabel,
        isOrdering,
        isOrderDetailOpen,
        activeTab,
        users,
        usersLoading,
        usersError,
        usersQuery,
        orders,
        ordersMeta,
        ordersLoading,
        ordersError,
        ordersDateFrom,
        ordersDateTo,
        ordersStatus,
        selectedOrderDetail,
        orderDetailLoading,
        orderDetailError,
        loadUsers,
        loadOrders,
        handleUsersSearchInput,
        handleOrdersDateFromChange,
        handleOrdersDateToChange,
        handleOrdersStatusChange,
        setSelectedConsumer,
        setActiveTab,
        openOrderDetail,
        closeOrderDetail,
        selectUser,
        clearTargetUser,
        initManualOrderSession,
        getTargetMaxUserId,
    };
}
