<script setup>
/**
 * Админ-shell: переключатель разделов + feature roots.
 */
import { onMounted, ref } from 'vue';
import AdminSectionNav from '../components/admin/AdminSectionNav.vue';
import { useAdminChrome } from '../composables/useAdminChrome';
import { useAuth } from '../composables/useAuth';
import { ADMIN_SECTIONS } from '../constants/views';
import { getStartParam } from '../bridge/maxBridge';
import { resolveOrderChatDeepLinkOrderId } from '../utils/orderChatDeepLink';
import ManualOrdersRoot from './admin/ManualOrdersRoot.vue';
import MenuAdminRoot from './admin/MenuAdminRoot.vue';
import OrdersAdminRoot from './admin/OrdersAdminRoot.vue';

const {
    adminSection,
    hasOrderReviewRoles,
    hasMenuManagerRole,
    hasMaxManagerRole,
    showAdminSectionSwitcher,
} = useAuth();

const { sectionNavVisible } = useAdminChrome();

/** Deep link order_{id}_chat — открыть карточку в разделе заказов */
const deepLinkOrderId = ref(null);

/**
 * @param {string} section
 */
function handleAdminSectionChange(section) {
    if (adminSection.value === section) {
        return;
    }

    adminSection.value = section;
    deepLinkOrderId.value = null;
}

onMounted(() => {
    const orderId = resolveOrderChatDeepLinkOrderId({ getStartParam });

    if (orderId !== null && hasOrderReviewRoles.value) {
        adminSection.value = ADMIN_SECTIONS.orders;
        deepLinkOrderId.value = orderId;
    }
});
</script>

<template>
    <div class="flex h-dvh flex-col overflow-hidden">
        <AdminSectionNav
            v-if="showAdminSectionSwitcher && sectionNavVisible"
            :admin-section="adminSection"
            :has-order-review-roles="hasOrderReviewRoles"
            :has-max-manager-role="hasMaxManagerRole"
            :has-menu-manager-role="hasMenuManagerRole"
            @change="handleAdminSectionChange"
        />
        <!-- safe-area один раз: на nav либо spacer -->
        <div
            v-else
            class="safe-area-top shrink-0"
            aria-hidden="true"
        />

        <div class="flex min-h-0 flex-1 flex-col overflow-hidden">
            <MenuAdminRoot
                v-if="adminSection === ADMIN_SECTIONS.menu && hasMenuManagerRole"
            />

            <ManualOrdersRoot
                v-else-if="adminSection === ADMIN_SECTIONS.manualOrders && hasMaxManagerRole"
            />

            <OrdersAdminRoot
                v-else-if="hasOrderReviewRoles"
                :deep-link-order-id="deepLinkOrderId"
            />
        </div>
    </div>
</template>
