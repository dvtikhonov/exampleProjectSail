<script setup>
/**
 * Раздел проверки заказов: очередь address/composition + деталь заказа.
 */
import { onMounted, onUnmounted, watch } from 'vue';
import { getStartParam } from '../../bridge/maxBridge';
import { useAdminChrome } from '../../composables/useAdminChrome';
import { useAdminFlow } from '../../composables/useAdminFlow';
import { useAuth } from '../../composables/useAuth';
import { createChatMessagesReadHandler, useMaxBackButton } from '../../composables/useMaxBackButton';
import { ADMIN_SECTIONS, ADMIN_VIEWS } from '../../constants/views';
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
    initAdminSession();

    const orderId = props.deepLinkOrderId ?? resolveOrderChatDeepLinkOrderId({ getStartParam });

    if (orderId !== null && adminSection.value === ADMIN_SECTIONS.orders) {
        await openAdminOrderById(orderId);
    }

    back.setupBackButton();
});

onUnmounted(() => {
    sectionNavVisible.value = true;
});
</script>

<template>
    <AdminOrderDetailPage
        v-if="adminView === ADMIN_VIEWS.detail && selectedAdminOrder"
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
        :orders="adminOrders"
        :loading="adminOrdersLoading"
        :error="adminOrdersError"
        :refreshing="adminOrdersRefreshing"
        @change-scope="handleAdminScopeChange"
        @select-order="openAdminOrder"
        @refresh="loadAdminOrders({ refreshing: true })"
    />
</template>
