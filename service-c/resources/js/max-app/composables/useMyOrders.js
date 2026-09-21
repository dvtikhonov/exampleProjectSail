/**
 * История заказов клиента: список, детали, счётчик непрочитанных.
 */
import { computed, ref } from 'vue';
import { extractErrorMessage, fetchMyOrders, fetchOrder } from '../api';
import { VIEWS } from '../constants/views';

const DEFAULT_PER_PAGE = 20;

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

/** @returns {{ current_page: number, per_page: number, total: number, last_page: number }} */
function emptyOrdersMeta() {
    return {
        current_page: 1,
        per_page: DEFAULT_PER_PAGE,
        total: 0,
        last_page: 1,
    };
}

/**
 * @param {object} deps
 * @param {import('vue').Ref<string>} deps.currentView — текущий экран клиента
 */
export function useMyOrders({ currentView }) {
    const myOrders = ref([]);
    const myOrdersMeta = ref(emptyOrdersMeta());
    const myOrdersLoading = ref(false);
    const myOrdersRefreshing = ref(false);
    const myOrdersLoadingMore = ref(false);
    const myOrdersError = ref('');

    const selectedOrderId = ref(null);
    const orderDetail = ref(null);
    const orderDetailLoading = ref(false);
    const orderDetailError = ref('');

    let detailSeq = 0;
    /** @type {AbortController|null} */
    let detailAbortController = null;

    const ordersUnreadCount = computed(() =>
        myOrders.value.reduce((sum, order) => sum + (order.unread_count ?? 0), 0),
    );

    const hasMoreOrders = computed(
        () => myOrdersMeta.value.current_page < myOrdersMeta.value.last_page,
    );

    function abortOrderDetail() {
        if (detailAbortController !== null) {
            detailAbortController.abort();
            detailAbortController = null;
        }
    }

    /**
     * @param {{ refreshing?: boolean, silent?: boolean }} [options]
     */
    async function loadMyOrders({ refreshing = false, silent = false } = {}) {
        if (refreshing) {
            myOrdersRefreshing.value = true;
        } else if (!silent) {
            myOrdersLoading.value = true;
        }

        myOrdersError.value = '';

        try {
            const result = await fetchMyOrders({ page: 1, perPage: DEFAULT_PER_PAGE });
            myOrders.value = result.orders;
            myOrdersMeta.value = result.meta;
        } catch (error) {
            if (!silent) {
                myOrdersError.value = extractErrorMessage(error);
            }
        } finally {
            myOrdersLoading.value = false;
            myOrdersRefreshing.value = false;
        }
    }

    async function loadMoreMyOrders() {
        if (!hasMoreOrders.value || myOrdersLoadingMore.value || myOrdersLoading.value) {
            return;
        }

        myOrdersLoadingMore.value = true;
        myOrdersError.value = '';

        try {
            const nextPage = myOrdersMeta.value.current_page + 1;
            const result = await fetchMyOrders({
                page: nextPage,
                perPage: myOrdersMeta.value.per_page || DEFAULT_PER_PAGE,
            });
            myOrders.value = [...myOrders.value, ...result.orders];
            myOrdersMeta.value = result.meta;
        } catch (error) {
            myOrdersError.value = extractErrorMessage(error);
        } finally {
            myOrdersLoadingMore.value = false;
        }
    }

    function goToMyOrders() {
        abortOrderDetail();
        detailSeq += 1;
        currentView.value = VIEWS.orderList;
        selectedOrderId.value = null;
        orderDetail.value = null;
        orderDetailLoading.value = false;
        loadMyOrders();
    }

    async function openOrderDetail(orderId) {
        abortOrderDetail();

        const controller = new AbortController();
        detailAbortController = controller;
        const mySeq = ++detailSeq;

        selectedOrderId.value = orderId;
        currentView.value = VIEWS.orderDetail;
        orderDetail.value = null;
        orderDetailError.value = '';
        orderDetailLoading.value = true;

        try {
            const detail = await fetchOrder(orderId, { signal: controller.signal });

            if (mySeq !== detailSeq || selectedOrderId.value !== orderId) {
                return;
            }

            orderDetail.value = detail;
        } catch (error) {
            if (isRequestCanceled(error) || mySeq !== detailSeq || selectedOrderId.value !== orderId) {
                return;
            }

            orderDetailError.value = extractErrorMessage(error);
        } finally {
            if (mySeq === detailSeq) {
                orderDetailLoading.value = false;
            }
        }
    }

    /**
     * @param {{ id: number }} order
     */
    function handleSelectOrder(order) {
        openOrderDetail(order.id);
    }

    function closeOrderDetail() {
        abortOrderDetail();
        detailSeq += 1;
        currentView.value = VIEWS.orderList;
        selectedOrderId.value = null;
        orderDetail.value = null;
        orderDetailError.value = '';
        orderDetailLoading.value = false;
        loadMyOrders();
    }

    /**
     * @param {{ id: number|string }|null|undefined} order
     */
    function goToOrderFromConfirmation(order) {
        if (!order?.id) {
            return;
        }

        openOrderDetail(order.id);
    }

    /** Сброс выбранного заказа при возврате на главный экран */
    function resetOrderSelection() {
        abortOrderDetail();
        detailSeq += 1;
        selectedOrderId.value = null;
        orderDetail.value = null;
        orderDetailLoading.value = false;
    }

    return {
        myOrders,
        myOrdersMeta,
        myOrdersLoading,
        myOrdersRefreshing,
        myOrdersLoadingMore,
        myOrdersError,
        hasMoreOrders,
        selectedOrderId,
        orderDetail,
        orderDetailLoading,
        orderDetailError,
        ordersUnreadCount,
        loadMyOrders,
        loadMoreMyOrders,
        goToMyOrders,
        openOrderDetail,
        handleSelectOrder,
        closeOrderDetail,
        goToOrderFromConfirmation,
        resetOrderSelection,
    };
}
