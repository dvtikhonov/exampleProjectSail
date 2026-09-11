<script setup>
/**
 * Раздел «Заказы»: вкладки проверки (address/composition) и отчётов (max_manager).
 */
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { getStartParam } from '../../bridge/maxBridge';
import { useAdminChrome } from '../../composables/useAdminChrome';
import { useAdminFlow } from '../../composables/useAdminFlow';
import { useAuth } from '../../composables/useAuth';
import { createChatMessagesReadHandler, useMaxBackButton } from '../../composables/useMaxBackButton';
import { ADMIN_ORDERS_PANELS, ADMIN_SECTIONS, ADMIN_VIEWS } from '../../constants/views';
import AdminHomePage from '../../pages/admin/AdminHomePage.vue';
import AdminOrderDetailPage from '../../pages/admin/AdminOrderDetailPage.vue';
import { resolveOrderChatDeepLinkOrderId } from '../../utils/orderChatDeepLink';

const props = defineProps({
    /** Deep link order id из AdminAppShell (если уже разрешён при смене секции) */
    deepLinkOrderId: {
        type: Number,
        default: null,
    },
});

const {
    adminRoles,
    adminScope,
    adminSection,
    hasAdminRoles,
    hasMenuManagerRole,
    hasMaxManagerRole,
    hasOrderReviewRoles,
} = useAuth();

const { sectionNavVisible } = useAdminChrome();

const admin = useAdminFlow(adminScope);
const {
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
    handleAdminCompositionSaved,
} = admin;

/**
 * Стартовая вкладка: проверка при review-ролях, иначе отчёты.
 *
 * @returns {'review'|'reports'}
 */
function resolveDefaultOrdersPanel() {
    if (hasOrderReviewRoles.value) {
        return ADMIN_ORDERS_PANELS.review;
    }

    if (hasMaxManagerRole.value) {
        return ADMIN_ORDERS_PANELS.reports;
    }

    return ADMIN_ORDERS_PANELS.review;
}

/** @type {import('vue').Ref<'review'|'reports'>} */
const ordersPanel = ref(resolveDefaultOrdersPanel());

const showOrderDetail = computed(() => (
    hasOrderReviewRoles.value
    && ordersPanel.value === ADMIN_ORDERS_PANELS.review
    && adminView.value === ADMIN_VIEWS.detail
    && Boolean(selectedAdminOrder.value)
));

/**
 * @param {'review'|'reports'} panel
 */
function handleOrdersPanelChange(panel) {
    if (ordersPanel.value === panel) {
        return;
    }

    ordersPanel.value = panel;

    if (panel === ADMIN_ORDERS_PANELS.reports) {
        closeAdminOrderDetail();
    }
}

const back = useMaxBackButton({
    hasAdminRoles,
    adminSection,
    hasMenuManagerRole,
    admin,
});

const handleChatMessagesRead = createChatMessagesReadHandler({
    hasAdminRoles,
    admin,
});

watch(
    adminView,
    (view) => {
        sectionNavVisible.value = view !== ADMIN_VIEWS.detail;
    },
    { immediate: true },
);

onMounted(async () => {
    if (hasOrderReviewRoles.value) {
        initAdminSession();

        const orderId = props.deepLinkOrderId ?? resolveOrderChatDeepLinkOrderId({ getStartParam });

        if (orderId !== null && adminSection.value === ADMIN_SECTIONS.orders) {
            ordersPanel.value = ADMIN_ORDERS_PANELS.review;
            await openAdminOrderById(orderId);
        }
    }

    back.setupBackButton();
});

onUnmounted(() => {
    sectionNavVisible.value = true;
});
</script>

<template>
    <AdminOrderDetailPage
        v-if="showOrderDetail"
        class="min-h-0 flex-1"
        :order="adminOrderDetail ?? selectedAdminOrder"
        :scope="adminScope"
        :loading="adminDetailLoading"
        :action-loading="adminActionLoading"
        :action-error="adminActionError"
        :show-reject-modal="showRejectModal"
        :reject-target="adminRejectTarget"
        @back="closeAdminOrderDetail"
        @approve-address="handleAdminApproveAddress"
        @approve-payment="handleAdminApprovePayment"
        @approve-composition="handleAdminApproveComposition"
        @open-reject="openAdminRejectModal"
        @close-reject="closeAdminRejectModal"
        @reject="handleAdminReject"
        @composition-saved="handleAdminCompositionSaved"
        @messages-read="handleChatMessagesRead"
    />

    <AdminHomePage
        v-else
        :admin-roles="adminRoles"
        :active-scope="adminScope"
        :active-panel="ordersPanel"
        :orders="adminOrders"
        :loading="adminOrdersLoading"
        :error="adminOrdersError"
        :refreshing="adminOrdersRefreshing"
        :show-reports="hasMaxManagerRole"
        :show-order-queue="hasOrderReviewRoles"
        @change-scope="handleAdminScopeChange"
        @change-panel="handleOrdersPanelChange"
        @select-order="openAdminOrder"
        @refresh="loadAdminOrders({ refreshing: true })"
    />
</template>
