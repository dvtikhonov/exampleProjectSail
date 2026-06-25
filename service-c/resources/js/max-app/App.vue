<script setup>
/**
 * Корневой shell MAX mini-app.
 *
 * Связывает composables (auth, admin, client) и рендерит страницы.
 * Навигация без vue-router: currentView / adminView из composables.
 *
 * Refs/computed из composables деструктурируются на верхний уровень:
 * вложенные ref (auth.authLoading) в template не разворачиваются Vue.
 */
import { onMounted, ref } from 'vue';
import { useAdminFlow } from './composables/useAdminFlow';
import { useAuth } from './composables/useAuth';
import { useCart } from './composables/useCart';
import { useClientNavigation } from './composables/useClientNavigation';
import { createChatMessagesReadHandler, useMaxBackButton } from './composables/useMaxBackButton';
import { useMyOrders } from './composables/useMyOrders';
import { useRestaurantsMenu } from './composables/useRestaurantsMenu';
import { ADMIN_VIEWS, VIEWS } from './constants/views';
import CartPage from './pages/CartPage.vue';
import MenuPage from './pages/MenuPage.vue';
import OrderConfirmationPage from './pages/OrderConfirmationPage.vue';
import OrderDetailPage from './pages/OrderDetailPage.vue';
import OrderListPage from './pages/OrderListPage.vue';
import RestaurantList from './pages/RestaurantList.vue';
import AdminHomePage from './pages/admin/AdminHomePage.vue';
import AdminOrderDetailPage from './pages/admin/AdminOrderDetailPage.vue';

/** Текущий экран клиентского потока — общий ref для cart, orders, navigation */
const currentView = ref(VIEWS.restaurants);

const {
    authLoading,
    authError,
    adminRoles,
    hasAdminRoles,
    adminScope,
    initAuth,
} = useAuth();

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
    closeAdminOrderDetail,
    handleAdminApproveAddress,
    handleAdminApprovePayment,
    handleAdminApproveComposition,
    openAdminRejectModal,
    closeAdminRejectModal,
    handleAdminReject,
} = admin;

const cartFlow = useCart({ currentView });
const {
    cart,
    cartLoading,
    cartError,
    updatingItemId,
    clearingCart,
    submitting,
    savingAddress,
    submittedOrder,
    cartPageRef,
    cartItemCount,
    cartTotal,
    handleUpdateQuantity,
    handleRemoveItem,
    handleClearCart,
    handleDeliveryAddressInput,
    handleDeliveryAddressBlur,
    handleSubmitOrder,
} = cartFlow;

const restaurantsMenu = useRestaurantsMenu({ currentView, cart });
const {
    restaurants,
    restaurantsLoading,
    restaurantsError,
    menu,
    menuLoading,
    menuError,
    addingDishId,
    openRestaurant,
    handleAddToCart,
} = restaurantsMenu;

const orders = useMyOrders({ currentView });
const {
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
    handleSelectOrder,
    closeOrderDetail,
    goToOrderFromConfirmation,
} = orders;

const nav = useClientNavigation({ currentView, restaurantsMenu, cart: cartFlow, orders });
const { goToRestaurants, goToCart, bootstrapClient } = nav;

const back = useMaxBackButton({
    hasAdminRoles,
    admin,
    nav,
    cart: cartFlow,
    orders,
});

const handleChatMessagesRead = createChatMessagesReadHandler({
    hasAdminRoles,
    admin,
    nav,
    orders,
});

/** Стартовая последовательность: auth → admin или клиентские данные + deep link */
async function bootstrapApp() {
    await initAuth();

    if (!authError.value) {
        if (hasAdminRoles.value) {
            initAdminSession();
        } else {
            await bootstrapClient();
        }
    }

    back.setupBackButton();
}

onMounted(async () => {
    await bootstrapApp();
});
</script>

<template>
    <div class="mx-auto min-h-dvh max-w-lg bg-max-surface">
        <div
            v-if="authLoading"
            class="flex min-h-dvh items-center justify-center"
        >
            <div class="text-center">
                <div class="mx-auto h-10 w-10 animate-spin rounded-full border-4 border-max-primary border-t-transparent" />
                <p class="mt-4 text-sm text-max-muted">Авторизация…</p>
            </div>
        </div>

        <div
            v-else-if="authError"
            class="flex min-h-dvh flex-col items-center justify-center px-6 text-center"
        >
            <div class="mb-4 text-5xl">🔒</div>
            <h1 class="text-lg font-semibold text-gray-900">Не удалось войти</h1>
            <p class="mt-2 text-sm text-max-muted">{{ authError }}</p>
        </div>

        <template v-else>
            <template v-if="hasAdminRoles">
                <AdminOrderDetailPage
                    v-if="adminView === ADMIN_VIEWS.detail && selectedAdminOrder"
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

            <template v-else>
                <RestaurantList
                    v-if="currentView === VIEWS.restaurants"
                    :restaurants="restaurants"
                    :loading="restaurantsLoading"
                    :error="restaurantsError"
                    :cart-item-count="cartItemCount"
                    :orders-unread-count="ordersUnreadCount"
                    @select-restaurant="openRestaurant"
                    @open-cart="goToCart"
                    @open-orders="goToMyOrders"
                />

                <MenuPage
                    v-else-if="currentView === VIEWS.menu"
                    :menu="menu"
                    :loading="menuLoading"
                    :error="menuError"
                    :adding-dish-id="addingDishId"
                    :cart-item-count="cartItemCount"
                    :cart-total="cartTotal"
                    :orders-unread-count="ordersUnreadCount"
                    @add-to-cart="handleAddToCart"
                    @open-cart="goToCart"
                    @open-orders="goToMyOrders"
                />

                <CartPage
                    ref="cartPageRef"
                    v-else-if="currentView === VIEWS.cart"
                    :cart="cart"
                    :loading="cartLoading"
                    :error="cartError"
                    :submitting="submitting"
                    :updating-item-id="updatingItemId"
                    :saving-address="savingAddress"
                    :clearing="clearingCart"
                    :orders-unread-count="ordersUnreadCount"
                    @update-quantity="handleUpdateQuantity"
                    @remove-item="handleRemoveItem"
                    @clear-cart="handleClearCart"
                    @submit-order="handleSubmitOrder"
                    @go-back="back.handleBack"
                    @go-to-restaurants="goToRestaurants"
                    @delivery-address-input="handleDeliveryAddressInput"
                    @delivery-address-blur="handleDeliveryAddressBlur"
                    @open-orders="goToMyOrders"
                />

                <OrderListPage
                    v-else-if="currentView === VIEWS.orderList"
                    :orders="myOrders"
                    :loading="myOrdersLoading"
                    :error="myOrdersError"
                    :refreshing="myOrdersRefreshing"
                    @select-order="handleSelectOrder"
                    @refresh="loadMyOrders({ refreshing: true })"
                    @back="goToRestaurants"
                />

                <OrderDetailPage
                    v-else-if="currentView === VIEWS.orderDetail && selectedOrderId"
                    :order="orderDetail ?? { id: selectedOrderId }"
                    :loading="orderDetailLoading"
                    :error="orderDetailError"
                    @back="closeOrderDetail"
                    @messages-read="handleChatMessagesRead"
                />

                <OrderConfirmationPage
                    v-else-if="currentView === VIEWS.confirmation && submittedOrder"
                    :order="submittedOrder"
                    @back-to-restaurants="goToRestaurants"
                    @go-to-order="() => goToOrderFromConfirmation(submittedOrder)"
                />
            </template>
        </template>
    </div>
</template>
