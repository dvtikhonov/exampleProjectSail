<script setup>
/**
 * Общий блок оформления: ресторан → меню → корзина → confirmation.
 * Используется в ClientAppShell и ManualOrdersRoot.
 */
import { VIEWS } from '../../constants/views';
import CartPage from '../../pages/CartPage.vue';
import MenuPage from '../../pages/MenuPage.vue';
import OrderConfirmationPage from '../../pages/OrderConfirmationPage.vue';
import RestaurantListPage from '../../pages/RestaurantListPage.vue';

defineProps({
    currentView: {
        type: String,
        required: true,
    },
    manualOrderMode: {
        type: Boolean,
        default: false,
    },
    customerLabel: {
        type: String,
        default: '',
    },
    ordersUnreadCount: {
        type: Number,
        default: 0,
    },
    restaurants: {
        type: Array,
        default: () => [],
    },
    restaurantsLoading: {
        type: Boolean,
        default: false,
    },
    restaurantsError: {
        type: String,
        default: '',
    },
    menu: {
        type: Object,
        default: null,
    },
    menuLoading: {
        type: Boolean,
        default: false,
    },
    menuError: {
        type: String,
        default: '',
    },
    deliveryAddress: {
        type: String,
        default: '',
    },
    addingDishId: {
        type: [Number, String],
        default: null,
    },
    addingComboRef: {
        type: [Number, String],
        default: null,
    },
    cart: {
        type: Object,
        default: null,
    },
    cartLoading: {
        type: Boolean,
        default: false,
    },
    cartError: {
        type: String,
        default: '',
    },
    cartItemCount: {
        type: Number,
        default: 0,
    },
    cartTotal: {
        type: [Number, String],
        default: '0.00',
    },
    submitting: {
        type: Boolean,
        default: false,
    },
    updatingItemId: {
        type: [Number, String],
        default: null,
    },
    savingAddress: {
        type: Boolean,
        default: false,
    },
    clearingCart: {
        type: Boolean,
        default: false,
    },
    isSingleRestaurantMode: {
        type: Boolean,
        default: false,
    },
    submittedOrder: {
        type: Object,
        default: null,
    },
    /** Привязка ref CartPage для кнопки «Назад» (избегает unwrap Ref в props) */
    assignCartPageRef: {
        type: Function,
        required: true,
    },
    openRestaurant: {
        type: Function,
        required: true,
    },
    goToCart: {
        type: Function,
        required: true,
    },
    openOrders: {
        type: Function,
        default: null,
    },
    handleAddToCart: {
        type: Function,
        required: true,
    },
    handleAddComboToCart: {
        type: Function,
        required: true,
    },
    handleUpdateQuantity: {
        type: Function,
        required: true,
    },
    handleRemoveItem: {
        type: Function,
        required: true,
    },
    handleClearCart: {
        type: Function,
        required: true,
    },
    handleSubmitOrder: {
        type: Function,
        required: true,
    },
    handleDeliveryAddressInput: {
        type: Function,
        required: true,
    },
    handleDeliveryAddressBlur: {
        type: Function,
        required: true,
    },
    handleBack: {
        type: Function,
        required: true,
    },
    goHome: {
        type: Function,
        required: true,
    },
    /** Клиент: назад к ресторанам; manual: выход к списку пользователей */
    onConfirmationBack: {
        type: Function,
        required: true,
    },
    /** Только клиентский режим — переход к заказу после confirmation */
    onGoToOrder: {
        type: Function,
        default: null,
    },
});
</script>

<template>
    <RestaurantListPage
        v-if="currentView === VIEWS.restaurants"
        class="min-h-0 flex-1"
        :restaurants="restaurants"
        :loading="restaurantsLoading"
        :error="restaurantsError"
        :cart-item-count="cartItemCount"
        :manual-order-mode="manualOrderMode"
        :customer-label="customerLabel"
        :orders-unread-count="manualOrderMode ? 0 : ordersUnreadCount"
        @select-restaurant="openRestaurant"
        @open-cart="goToCart"
        @open-orders="openOrders?.()"
    />

    <MenuPage
        v-else-if="currentView === VIEWS.menu"
        class="min-h-0 flex-1"
        :menu="menu"
        :delivery-address="deliveryAddress"
        :loading="menuLoading"
        :error="menuError"
        :adding-dish-id="addingDishId"
        :adding-combo-ref="addingComboRef"
        :cart-item-count="cartItemCount"
        :cart-total="cartTotal"
        :orders-unread-count="manualOrderMode ? 0 : ordersUnreadCount"
        :saving-address="savingAddress"
        :manual-order-mode="manualOrderMode"
        :customer-label="customerLabel"
        @add-to-cart="handleAddToCart"
        @add-combo-to-cart="handleAddComboToCart"
        @open-cart="goToCart"
        @open-orders="openOrders?.()"
        @delivery-address-input="handleDeliveryAddressInput"
        @delivery-address-blur="handleDeliveryAddressBlur"
    />

    <CartPage
        :ref="assignCartPageRef"
        v-else-if="currentView === VIEWS.cart"
        class="min-h-0 flex-1"
        :cart="cart"
        :loading="cartLoading"
        :error="cartError"
        :submitting="submitting"
        :updating-item-id="updatingItemId"
        :saving-address="savingAddress"
        :clearing="clearingCart"
        :orders-unread-count="manualOrderMode ? 0 : ordersUnreadCount"
        :is-single-restaurant-mode="isSingleRestaurantMode"
        :manual-order-mode="manualOrderMode"
        @update-quantity="handleUpdateQuantity"
        @remove-item="handleRemoveItem"
        @clear-cart="handleClearCart"
        @submit-order="handleSubmitOrder"
        @go-back="handleBack"
        @go-to-restaurants="goHome"
        @delivery-address-input="handleDeliveryAddressInput"
        @delivery-address-blur="handleDeliveryAddressBlur"
        @open-orders="openOrders?.()"
    />

    <OrderConfirmationPage
        v-else-if="currentView === VIEWS.confirmation && submittedOrder"
        :order="submittedOrder"
        :is-single-restaurant-mode="isSingleRestaurantMode"
        :manual-order-mode="manualOrderMode"
        :customer-label="customerLabel"
        @back-to-restaurants="onConfirmationBack"
        @back-to-users="onConfirmationBack"
        @go-to-order="onGoToOrder?.(submittedOrder)"
    />
</template>
