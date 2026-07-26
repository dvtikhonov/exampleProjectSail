<script setup>
/**
 * Клиентский shell: корзина, меню, мои заказы, навигация.
 */
import { computed, onMounted, ref } from 'vue';
import { createClientCartTransport } from '../api/cartTransport';
import OrderingFlow from '../components/ordering/OrderingFlow.vue';
import { useCart } from '../composables/useCart';
import { useClientNavigation } from '../composables/useClientNavigation';
import { createChatMessagesReadHandler, useMaxBackButton } from '../composables/useMaxBackButton';
import { useMyOrders } from '../composables/useMyOrders';
import { useRestaurantsMenu } from '../composables/useRestaurantsMenu';
import { VIEWS } from '../constants/views';
import OrderDetailPage from '../pages/OrderDetailPage.vue';
import OrderListPage from '../pages/OrderListPage.vue';

const currentView = ref(VIEWS.restaurants);
const cartTransport = createClientCartTransport();

const cartFlow = useCart({ currentView, cartTransport });
const {
    cart,
    deliveryAddress,
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

const restaurantsMenu = useRestaurantsMenu({ currentView, cart, cartTransport });
const {
    restaurants,
    restaurantsLoading,
    restaurantsError,
    menu,
    menuLoading,
    menuError,
    addingDishId,
    addingComboRef,
    openRestaurant,
    handleAddToCart,
    handleAddComboToCart,
    isSingleRestaurantMode,
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
const { goHome, goToCart, bootstrapClient } = nav;

const hasAdminRoles = computed(() => false);
const adminSection = ref('');
const hasMenuManagerRole = computed(() => false);

const back = useMaxBackButton({
    hasAdminRoles,
    adminSection,
    hasMenuManagerRole,
    nav,
    cart: cartFlow,
    orders,
    isSingleRestaurantMode: restaurantsMenu.isSingleRestaurantMode,
});

const handleChatMessagesRead = createChatMessagesReadHandler({
    hasAdminRoles,
    nav,
    orders,
});

/**
 * @param {unknown} el
 */
function assignCartPageRef(el) {
    cartPageRef.value = el;
}

onMounted(async () => {
    await bootstrapClient();
    back.setupBackButton();
});
</script>

<template>
    <OrderingFlow
        :current-view="currentView"
        :orders-unread-count="ordersUnreadCount"
        :restaurants="restaurants"
        :restaurants-loading="restaurantsLoading"
        :restaurants-error="restaurantsError"
        :menu="menu"
        :menu-loading="menuLoading"
        :menu-error="menuError"
        :delivery-address="deliveryAddress"
        :adding-dish-id="addingDishId"
        :adding-combo-ref="addingComboRef"
        :cart="cart"
        :cart-loading="cartLoading"
        :cart-error="cartError"
        :cart-item-count="cartItemCount"
        :cart-total="cartTotal"
        :submitting="submitting"
        :updating-item-id="updatingItemId"
        :saving-address="savingAddress"
        :clearing-cart="clearingCart"
        :is-single-restaurant-mode="isSingleRestaurantMode"
        :submitted-order="submittedOrder"
        :assign-cart-page-ref="assignCartPageRef"
        :open-restaurant="openRestaurant"
        :go-to-cart="goToCart"
        :open-orders="goToMyOrders"
        :handle-add-to-cart="handleAddToCart"
        :handle-add-combo-to-cart="handleAddComboToCart"
        :handle-update-quantity="handleUpdateQuantity"
        :handle-remove-item="handleRemoveItem"
        :handle-clear-cart="handleClearCart"
        :handle-submit-order="handleSubmitOrder"
        :handle-delivery-address-input="handleDeliveryAddressInput"
        :handle-delivery-address-blur="handleDeliveryAddressBlur"
        :handle-back="back.handleBack"
        :go-home="goHome"
        :on-confirmation-back="goHome"
        :on-go-to-order="(order) => goToOrderFromConfirmation(order)"
    />

    <OrderListPage
        v-if="currentView === VIEWS.orderList"
        :orders="myOrders"
        :loading="myOrdersLoading"
        :error="myOrdersError"
        :refreshing="myOrdersRefreshing"
        @select-order="handleSelectOrder"
        @refresh="loadMyOrders({ refreshing: true })"
        @back="goHome"
    />

    <OrderDetailPage
        v-else-if="currentView === VIEWS.orderDetail && selectedOrderId"
        :order="orderDetail ?? { id: selectedOrderId }"
        :loading="orderDetailLoading"
        :error="orderDetailError"
        @back="closeOrderDetail"
        @messages-read="handleChatMessagesRead"
    />
</template>
