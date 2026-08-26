<script setup>
/**
 * Раздел ручных заказов: выбор пользователя + OrderingFlow от имени клиента.
 */
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { createManualCartTransport } from '../../api/cartTransport';
import OrderingFlow from '../../components/ordering/OrderingFlow.vue';
import { useAdminChrome } from '../../composables/useAdminChrome';
import { useAuth } from '../../composables/useAuth';
import { useCart } from '../../composables/useCart';
import { useClientNavigation } from '../../composables/useClientNavigation';
import { useManualOrder } from '../../composables/useManualOrder';
import { useMaxBackButton } from '../../composables/useMaxBackButton';
import { useRestaurantsMenu } from '../../composables/useRestaurantsMenu';
import { ADMIN_SECTIONS, VIEWS } from '../../constants/views';
import ManualOrderDetailPage from '../../pages/admin/ManualOrderDetailPage.vue';
import ManualOrderUserSelectPage from '../../pages/admin/ManualOrderUserSelectPage.vue';

const {
    adminSection,
    hasAdminRoles,
    hasMenuManagerRole,
    hasMaxManagerRole,
} = useAuth();

const { sectionNavVisible } = useAdminChrome();

const manualOrder = useManualOrder();
const {
    customerLabel,
    isOrdering: isManualOrdering,
    activeTab: manualOrderActiveTab,
    users: manualUsers,
    usersLoading: manualUsersLoading,
    usersError: manualUsersError,
    usersQuery: manualUsersQuery,
    selectedConsumer: manualSelectedConsumer,
    orders: manualOrders,
    ordersMeta: manualOrdersMeta,
    ordersLoading: manualOrdersLoading,
    ordersError: manualOrdersError,
    ordersDateFrom: manualOrdersDateFrom,
    ordersDateTo: manualOrdersDateTo,
    ordersStatus: manualOrdersStatus,
    selectedOrderDetail: manualOrderDetail,
    orderDetailLoading: manualOrderDetailLoading,
    orderDetailError: manualOrderDetailError,
    draftActionLoading: manualDraftActionLoading,
    draftActionError: manualDraftActionError,
    loadUsers: loadManualUsers,
    loadOrders: loadManualOrders,
    handleUsersSearchInput,
    handleOrdersDateFromChange,
    handleOrdersDateToChange,
    handleOrdersStatusChange,
    setSelectedConsumer: setManualSelectedConsumer,
    setActiveTab: setManualOrderActiveTab,
    openOrderDetail: openManualOrderDetail,
    closeOrderDetail: closeManualOrderDetail,
    clearDraftActionError: clearManualDraftActionError,
    completeDraftAfterScanning,
    moveDraftAfterScanningToCart,
    deleteDraftAfterScanning,
    startOrderingFromDraftCustomer,
    selectUser: selectManualUser,
    clearTargetUser: clearManualTargetUser,
    initManualOrderSession,
    getTargetMaxUserId: getManualTargetMaxUserId,
} = manualOrder;

const currentView = ref(VIEWS.restaurants);
/** Дата доставки из черновика после сканирования для подстановки в ручную корзину */
const preferredCartDeliveryDate = ref(null);
const cartTransport = createManualCartTransport(getManualTargetMaxUserId);

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
    resetLocalCartState,
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
    loadRestaurants,
    resetRestaurantSelection,
} = restaurantsMenu;

/** Stub «мои заказы»: в manual-потоке клиентский список не нужен */
const ordersStub = {
    resetOrderSelection() {},
    loadMyOrders: async () => {},
};

const nav = useClientNavigation({
    currentView,
    restaurantsMenu,
    cart: cartFlow,
    orders: ordersStub,
});
const { goHome, goToCart } = nav;

/** Выход из оформления ручного заказа к списку клиентов */
function handleManualExitOrdering() {
    preferredCartDeliveryDate.value = null;
    clearManualTargetUser();
    resetLocalCartState();
    resetRestaurantSelection();
    currentView.value = VIEWS.restaurants;
    loadManualUsers();
}

/**
 * @param {object} user
 */
async function handleManualSelectUser(user) {
    preferredCartDeliveryDate.value = null;
    selectManualUser(user);
    resetLocalCartState();
    resetRestaurantSelection();
    currentView.value = VIEWS.restaurants;

    await Promise.all([
        loadRestaurants(),
        cartFlow.loadCart(),
    ]);

    if (isSingleRestaurantMode.value && restaurants.value[0]) {
        await openRestaurant(restaurants.value[0]);
    }
}

/** Запуск оформления для выбранного в UI потребителя */
async function handleManualStartOrder() {
    const user = manualSelectedConsumer.value;

    if (!user) {
        return;
    }

    await handleManualSelectUser(user);
}

/**
 * После «Выполнить»: закрыть деталку и обновить список.
 */
async function handleCompleteDraftAfterScanning() {
    const orderId = manualOrderDetail.value?.id;

    if (!(await completeDraftAfterScanning(orderId))) {
        return;
    }

    closeManualOrderDetail();
    loadManualOrders();
}

/**
 * После «Удалить»: закрыть деталку и обновить список.
 */
async function handleDeleteDraftAfterScanning() {
    const orderId = manualOrderDetail.value?.id;

    if (!(await deleteDraftAfterScanning(orderId))) {
        return;
    }

    closeManualOrderDetail();
    loadManualOrders();
}

/**
 * После «В корзину»: выбрать потребителя заказа, открыть вкладку создания и экран корзины.
 */
async function handleMoveDraftAfterScanningToCart() {
    const order = manualOrderDetail.value;
    const customer = order?.customer ?? null;
    const orderDeliveryDate = typeof order?.delivery_date === 'string'
        ? order.delivery_date.trim()
        : '';
    const result = await moveDraftAfterScanningToCart(order?.id);

    if (result === null) {
        return;
    }

    const user = {
        ...(customer && typeof customer === 'object' ? customer : {}),
        max_user_id: result.customerMaxUserId ?? customer?.max_user_id,
    };

    const resultDeliveryDate = typeof result.deliveryDate === 'string'
        ? result.deliveryDate.trim()
        : (typeof result.cart?.delivery_date === 'string' ? result.cart.delivery_date.trim() : '');

    preferredCartDeliveryDate.value = resultDeliveryDate || orderDeliveryDate || null;

    resetLocalCartState();
    resetRestaurantSelection();
    currentView.value = VIEWS.cart;

    if (!startOrderingFromDraftCustomer(user)) {
        preferredCartDeliveryDate.value = null;
        currentView.value = VIEWS.restaurants;
        closeManualOrderDetail();
        loadManualOrders();

        return;
    }

    await Promise.all([
        loadRestaurants(),
        cartFlow.loadCart(),
    ]);

    restaurantsMenu.syncSelectedRestaurantFromCart();
}

/**
 * @param {unknown} el
 */
function assignCartPageRef(el) {
    cartPageRef.value = el;
}

const back = useMaxBackButton({
    hasAdminRoles,
    adminSection,
    hasMenuManagerRole,
    hasMaxManagerRole,
    manualOrder,
    onManualExitOrdering: handleManualExitOrdering,
    nav,
    cart: cartFlow,
    isSingleRestaurantMode: restaurantsMenu.isSingleRestaurantMode,
});

const showUserSelect = computed(() =>
    !manualOrderDetail.value
    && !manualOrderDetailLoading.value
    && !manualOrderDetailError.value
    && !isManualOrdering.value,
);

const showOrdering = computed(() =>
    !(manualOrderDetail.value || manualOrderDetailLoading.value || manualOrderDetailError.value)
    && isManualOrdering.value,
);

const showOrderDetail = computed(() =>
    Boolean(manualOrderDetail.value || manualOrderDetailLoading.value || manualOrderDetailError.value),
);

watch(
    [showOrderDetail, showOrdering],
    ([detail, ordering]) => {
        sectionNavVisible.value = !(detail || ordering);
    },
    { immediate: true },
);

onMounted(() => {
    if (adminSection.value === ADMIN_SECTIONS.manualOrders) {
        initManualOrderSession();
    }

    back.setupBackButton();
});

onUnmounted(() => {
    sectionNavVisible.value = true;
    preferredCartDeliveryDate.value = null;
    clearManualTargetUser();
    resetLocalCartState();
});
</script>

<template>
    <ManualOrderDetailPage
        v-if="showOrderDetail"
        class="min-h-0 flex-1"
        :order="manualOrderDetail"
        :loading="manualOrderDetailLoading"
        :error="manualOrderDetailError"
        :action-loading="manualDraftActionLoading"
        :action-error="manualDraftActionError"
        @back="closeManualOrderDetail"
        @complete="handleCompleteDraftAfterScanning"
        @move-to-cart="handleMoveDraftAfterScanningToCart"
        @delete="handleDeleteDraftAfterScanning"
        @clear-action-error="clearManualDraftActionError"
    />

    <KeepAlive>
        <ManualOrderUserSelectPage
            v-if="showUserSelect"
            class="min-h-0 flex-1"
            :users="manualUsers"
            :loading="manualUsersLoading"
            :error="manualUsersError"
            :query="manualUsersQuery"
            :selected-consumer="manualSelectedConsumer"
            :selected-consumer-id="manualSelectedConsumer?.max_user_id ?? ''"
            :active-tab="manualOrderActiveTab"
            :orders="manualOrders"
            :orders-loading="manualOrdersLoading"
            :orders-error="manualOrdersError"
            :orders-date-from="manualOrdersDateFrom"
            :orders-date-to="manualOrdersDateTo"
            :orders-status="manualOrdersStatus"
            :orders-total="manualOrdersMeta.total"
            :orders-total-amount="manualOrdersMeta.total_amount"
            @search="handleUsersSearchInput"
            @select-consumer="setManualSelectedConsumer"
            @start-order="handleManualStartOrder"
            @refresh="loadManualUsers"
            @change-tab="setManualOrderActiveTab"
            @orders-date-from="handleOrdersDateFromChange"
            @orders-date-to="handleOrdersDateToChange"
            @orders-status="handleOrdersStatusChange"
            @orders-refresh="loadManualOrders"
            @select-order="openManualOrderDetail"
        />
    </KeepAlive>

    <OrderingFlow
        v-if="showOrdering"
        :current-view="currentView"
        :manual-order-mode="true"
        :customer-label="customerLabel"
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
        :preferred-delivery-date="preferredCartDeliveryDate"
        :assign-cart-page-ref="assignCartPageRef"
        :open-restaurant="openRestaurant"
        :go-to-cart="goToCart"
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
        :on-confirmation-back="handleManualExitOrdering"
    />
</template>
