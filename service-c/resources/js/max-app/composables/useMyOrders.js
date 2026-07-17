/**
 * История заказов клиента: список, детали, счётчик непрочитанных.
 */
import { computed, ref } from 'vue';
import { extractErrorMessage, fetchMyOrders, fetchOrder } from '../api/foodClient';
import { VIEWS } from '../constants/views';

/**
 * @param {object} deps
 * @param {import('vue').Ref<string>} deps.currentView — текущий экран клиента
 */
export function useMyOrders({ currentView }) {
    const myOrders = ref([]);
    const myOrdersLoading = ref(false);
    const myOrdersRefreshing = ref(false);
    const myOrdersError = ref('');

    const selectedOrderId = ref(null);
    const orderDetail = ref(null);
    const orderDetailLoading = ref(false);
    const orderDetailError = ref('');

    const ordersUnreadCount = computed(() =>
        myOrders.value.reduce((sum, order) => sum + (order.unread_count ?? 0), 0),
    );

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
            myOrders.value = await fetchMyOrders();
        } catch (error) {
            if (!silent) {
                myOrdersError.value = extractErrorMessage(error);
            }
        } finally {
            myOrdersLoading.value = false;
            myOrdersRefreshing.value = false;
        }
    }

    function goToMyOrders() {
        currentView.value = VIEWS.orderList;
        selectedOrderId.value = null;
        orderDetail.value = null;
        loadMyOrders();
    }

    async function openOrderDetail(orderId) {
        selectedOrderId.value = orderId;
        currentView.value = VIEWS.orderDetail;
        orderDetail.value = null;
        orderDetailError.value = '';
        orderDetailLoading.value = true;

        try {
            orderDetail.value = await fetchOrder(orderId);
        } catch (error) {
            orderDetailError.value = extractErrorMessage(error);
        } finally {
            orderDetailLoading.value = false;
        }
    }

    /**
     * @param {{ id: number }} order
     */
    function handleSelectOrder(order) {
        openOrderDetail(order.id);
    }

    function closeOrderDetail() {
        currentView.value = VIEWS.orderList;
        selectedOrderId.value = null;
        orderDetail.value = null;
        orderDetailError.value = '';
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
        selectedOrderId.value = null;
        orderDetail.value = null;
    }

    return {
        myOrders,
        myOrdersLoading,
        myOrdersRefreshing,
        myOrdersError,
        selectedOrderId,
        orderDetail,
        orderDetailLoading,
        orderDetailError,
        ordersUnreadCount,
        loadMyOrders,
        goToMyOrders,
        openOrderDetail,
        handleSelectOrder,
        closeOrderDetail,
        goToOrderFromConfirmation,
        resetOrderSelection,
    };
}
