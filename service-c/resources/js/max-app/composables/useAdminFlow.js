/**
 * Админ-поток: очередь заказов на проверку, карточка, approve/reject.
 */
import { ref } from 'vue';
import {
    approveOrderAddress,
    approveOrderComposition,
    approveOrderPayment,
    extractErrorMessage,
    fetchAdminOrder,
    fetchAdminOrders,
    rejectOrderAddress,
    rejectOrderComposition,
    rejectOrderPayment,
} from '../api/foodClient';
import { ADMIN_VIEWS } from '../constants/views';

/**
 * @param {import('vue').Ref<string>} adminScope — активная вкладка (address / composition)
 * @returns {object} Состояние и обработчики админ-интерфейса
 */
export function useAdminFlow(adminScope) {
    const adminView = ref(ADMIN_VIEWS.list);

    const adminOrders = ref([]);
    const adminOrdersLoading = ref(false);
    const adminOrdersRefreshing = ref(false);
    const adminOrdersError = ref('');

    const selectedAdminOrder = ref(null);
    const adminOrderDetail = ref(null);
    const adminDetailLoading = ref(false);
    const adminActionLoading = ref(false);
    const adminActionError = ref('');
    const showRejectModal = ref(false);
    const adminRejectTarget = ref('address');

    /** Сброс состояния и загрузка очереди при входе проверяющего */
    function initAdminSession() {
        adminView.value = ADMIN_VIEWS.list;
        selectedAdminOrder.value = null;
        adminOrderDetail.value = null;
        adminActionError.value = '';
        showRejectModal.value = false;
        loadAdminOrders();
    }

    /**
     * @param {{ refreshing?: boolean, silent?: boolean }} [options]
     */
    async function loadAdminOrders({ refreshing = false, silent = false } = {}) {
        if (refreshing) {
            adminOrdersRefreshing.value = true;
        } else if (!silent) {
            adminOrdersLoading.value = true;
        }

        adminOrdersError.value = '';

        try {
            adminOrders.value = await fetchAdminOrders(adminScope.value);
        } catch (error) {
            adminOrdersError.value = extractErrorMessage(error);
        } finally {
            adminOrdersLoading.value = false;
            adminOrdersRefreshing.value = false;
        }
    }

    async function handleAdminScopeChange(scope) {
        if (adminScope.value === scope) {
            return;
        }

        adminScope.value = scope;
        adminView.value = ADMIN_VIEWS.list;
        selectedAdminOrder.value = null;
        adminOrderDetail.value = null;
        await loadAdminOrders();
    }

    async function openAdminOrder(order) {
        selectedAdminOrder.value = order;
        adminView.value = ADMIN_VIEWS.detail;
        adminOrderDetail.value = null;
        adminActionError.value = '';
        showRejectModal.value = false;
        adminDetailLoading.value = true;

        try {
            adminOrderDetail.value = await fetchAdminOrder(order.id, adminScope.value);
        } catch (error) {
            adminActionError.value = extractErrorMessage(error);
        } finally {
            adminDetailLoading.value = false;
        }
    }

    /**
     * Открыть карточку заказа по id (deep link из уведомления MAX).
     *
     * @param {number} orderId
     */
    async function openAdminOrderById(orderId) {
        await openAdminOrder({ id: orderId });
    }

    function closeAdminOrderDetail() {
        adminView.value = ADMIN_VIEWS.list;
        selectedAdminOrder.value = null;
        adminOrderDetail.value = null;
        adminActionError.value = '';
        showRejectModal.value = false;
        adminRejectTarget.value = 'address';
        loadAdminOrders();
    }

    /**
     * @param {object} order
     */
    function isAddressScopeReviewComplete(order) {
        return order.address_review_status !== 'pending' && order.payment_review_status !== 'pending';
    }

    async function handleAdminApproveAddress() {
        if (!selectedAdminOrder.value) {
            return;
        }

        adminActionLoading.value = true;
        adminActionError.value = '';

        try {
            const order = await approveOrderAddress(selectedAdminOrder.value.id);
            adminOrderDetail.value = order;
            selectedAdminOrder.value = order;

            if (isAddressScopeReviewComplete(order)) {
                closeAdminOrderDetail();
            } else {
                await loadAdminOrders({ silent: true });
            }
        } catch (error) {
            adminActionError.value = extractErrorMessage(error);
        } finally {
            adminActionLoading.value = false;
        }
    }

    async function handleAdminApprovePayment() {
        if (!selectedAdminOrder.value) {
            return;
        }

        adminActionLoading.value = true;
        adminActionError.value = '';

        try {
            const order = await approveOrderPayment(selectedAdminOrder.value.id);
            adminOrderDetail.value = order;
            selectedAdminOrder.value = order;

            if (isAddressScopeReviewComplete(order)) {
                closeAdminOrderDetail();
            } else {
                await loadAdminOrders({ silent: true });
            }
        } catch (error) {
            adminActionError.value = extractErrorMessage(error);
        } finally {
            adminActionLoading.value = false;
        }
    }

    async function handleAdminApproveComposition() {
        if (!selectedAdminOrder.value) {
            return;
        }

        adminActionLoading.value = true;
        adminActionError.value = '';

        try {
            await approveOrderComposition(selectedAdminOrder.value.id);
            closeAdminOrderDetail();
        } catch (error) {
            adminActionError.value = extractErrorMessage(error);
        } finally {
            adminActionLoading.value = false;
        }
    }

    /**
     * @param {'address'|'payment'|'composition'} target
     */
    function openAdminRejectModal(target) {
        adminActionError.value = '';
        adminRejectTarget.value = target;
        showRejectModal.value = true;
    }

    function closeAdminRejectModal() {
        if (!adminActionLoading.value) {
            showRejectModal.value = false;
        }
    }

    async function handleAdminReject(comment) {
        if (!selectedAdminOrder.value) {
            return;
        }

        adminActionLoading.value = true;
        adminActionError.value = '';

        try {
            const rejectByTarget = {
                address: rejectOrderAddress,
                payment: rejectOrderPayment,
                composition: rejectOrderComposition,
            };
            const reject = rejectByTarget[adminRejectTarget.value] ?? rejectOrderAddress;
            await reject(selectedAdminOrder.value.id, comment);
            showRejectModal.value = false;
            closeAdminOrderDetail();
        } catch (error) {
            adminActionError.value = extractErrorMessage(error);
        } finally {
            adminActionLoading.value = false;
        }
    }

    return {
        adminView,
        adminOrders,
        adminOrdersLoading,
        adminOrdersRefreshing,
        adminOrdersError,
        selectedAdminOrder,
        adminOrderDetail,
        adminDetailLoading,
        adminActionLoading,
        adminActionError,
        showRejectModal,
        adminRejectTarget,
        initAdminSession,
        loadAdminOrders,
        handleAdminScopeChange,
        openAdminOrder,
        openAdminOrderById,
        closeAdminOrderDetail,
        handleAdminApproveAddress,
        handleAdminApprovePayment,
        handleAdminApproveComposition,
        openAdminRejectModal,
        closeAdminRejectModal,
        handleAdminReject,
    };
}
