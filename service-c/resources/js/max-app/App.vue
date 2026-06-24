<script setup>
/**
 * Корневой компонент MAX mini-app.
 *
 * Управляет навигацией без vue-router: переключение экранов через currentView / adminView.
 * При старте авторизуется через initData из MAX Bridge; при наличии admin_roles
 * показывает админ-интерфейс проверки заказов вместо клиентского потока.
 */
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import {
    addToCart,
    approveOrderAddress,
    approveOrderComposition,
    authenticate,
    clearCart,
    extractErrorMessage,
    fetchAdminOrder,
    fetchAdminOrders,
    fetchCart,
    fetchMenu,
    fetchMyOrders,
    fetchOrder,
    fetchRestaurants,
    rejectOrderAddress,
    rejectOrderComposition,
    removeCartItem,
    submitOrder,
    updateCartDeliveryAddress,
    updateCartItem,
} from './api/foodClient';
import {
    bindBackButton,
    closeMaxApp,
    getInitData,
    getPlatform,
    hideBackButton,
} from './bridge/maxBridge';
import CartPage from './pages/CartPage.vue';
import MenuPage from './pages/MenuPage.vue';
import OrderConfirmationPage from './pages/OrderConfirmationPage.vue';
import OrderDetailPage from './pages/OrderDetailPage.vue';
import OrderListPage from './pages/OrderListPage.vue';
import RestaurantList from './pages/RestaurantList.vue';
import AdminHomePage from './pages/admin/AdminHomePage.vue';
import AdminOrderDetailPage from './pages/admin/AdminOrderDetailPage.vue';

/** Роли администратора, определяющие доступные вкладки проверки */
const ROLE_ADDRESS = 'address_reviewer';
const ROLE_COMPOSITION = 'composition_reviewer';

/** Экраны клиентского потока (ресторан → меню → корзина → заказ) */
const VIEWS = {
    restaurants: 'restaurants',
    menu: 'menu',
    cart: 'cart',
    confirmation: 'confirmation',
    orderList: 'orderList',
    orderDetail: 'orderDetail',
};

/** Экраны админ-потока (список очереди / карточка заказа) */
const ADMIN_VIEWS = {
    list: 'list',
    detail: 'detail',
};

// --- Состояние навигации ---
const currentView = ref(VIEWS.restaurants);
const adminView = ref(ADMIN_VIEWS.list);
const adminScope = ref('address');
const adminRoles = ref([]);

// --- Админ: очередь и карточка заказа ---
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

// --- Авторизация ---
const authLoading = ref(true);
const authError = ref('');

// --- Клиент: рестораны и меню ---
const restaurants = ref([]);
const restaurantsLoading = ref(false);
const restaurantsError = ref('');

const selectedRestaurant = ref(null);
const menu = ref(null);
const menuLoading = ref(false);
const menuError = ref('');
const addingDishId = ref(null);

// --- Клиент: корзина и оформление ---
const cart = ref(null);
const cartLoading = ref(false);
const cartError = ref('');
const updatingItemId = ref(null);
const clearingCart = ref(false);
const submitting = ref(false);
const savingAddress = ref(false);

const submittedOrder = ref(null);
const cartPageRef = ref(null);

// --- Клиент: история заказов и чат ---
const myOrders = ref([]);
const myOrdersLoading = ref(false);
const myOrdersRefreshing = ref(false);
const myOrdersError = ref('');

const selectedOrderId = ref(null);
const orderDetail = ref(null);
const orderDetailLoading = ref(false);
const orderDetailError = ref('');

/** Снимает обработчик кнопки «Назад» MAX Bridge при смене экрана */
let unbindBackButton = () => {};
/** Таймер debounce для автосохранения адреса доставки (500 мс) */
let addressDebounceTimer = null;

const cartItemCount = computed(() => {
    if (!cart.value?.items) {
        return 0;
    }

    return cart.value.items.reduce((sum, item) => sum + item.quantity, 0);
});

const cartTotal = computed(() => cart.value?.total ?? '0.00');

const ordersUnreadCount = computed(() =>
    myOrders.value.reduce((sum, order) => sum + (order.unread_count ?? 0), 0),
);

const hasAdminRoles = computed(() => adminRoles.value.length > 0);

/** Авторизация по initData из MAX; заполняет adminRoles для выбора режима UI */
async function initAuth() {
    authLoading.value = true;
    authError.value = '';

    try {
        const initData = getInitData();

        if (!initData) {
            throw new Error('Не удалось получить initData от MAX. Откройте приложение через MAX.');
        }

        const authData = await authenticate(initData);
        adminRoles.value = authData.user?.admin_roles ?? [];
        adminScope.value = resolveDefaultAdminScope(adminRoles.value);
    } catch (error) {
        authError.value = extractErrorMessage(error);
    } finally {
        authLoading.value = false;
    }
}

/**
 * @param {string[]} roles
 */
function resolveDefaultAdminScope(roles) {
    if (roles.includes(ROLE_ADDRESS)) {
        return 'address';
    }

    if (roles.includes(ROLE_COMPOSITION)) {
        return 'composition';
    }

    return 'address';
}

/** Сброс админ-состояния и загрузка очереди при входе с ролью проверяющего */
function initAdminSession() {
    adminView.value = ADMIN_VIEWS.list;
    selectedAdminOrder.value = null;
    adminOrderDetail.value = null;
    adminActionError.value = '';
    showRejectModal.value = false;
    loadAdminOrders();
}

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

function closeAdminOrderDetail() {
    adminView.value = ADMIN_VIEWS.list;
    selectedAdminOrder.value = null;
    adminOrderDetail.value = null;
    adminActionError.value = '';
    showRejectModal.value = false;
    loadAdminOrders();
}

async function handleAdminApprove() {
    if (!selectedAdminOrder.value) {
        return;
    }

    adminActionLoading.value = true;
    adminActionError.value = '';

    try {
        const approve =
            adminScope.value === 'address' ? approveOrderAddress : approveOrderComposition;
        await approve(selectedAdminOrder.value.id);
        closeAdminOrderDetail();
    } catch (error) {
        adminActionError.value = extractErrorMessage(error);
    } finally {
        adminActionLoading.value = false;
    }
}

function openAdminRejectModal() {
    adminActionError.value = '';
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
        const reject =
            adminScope.value === 'address' ? rejectOrderAddress : rejectOrderComposition;
        await reject(selectedAdminOrder.value.id, comment);
        showRejectModal.value = false;
        closeAdminOrderDetail();
    } catch (error) {
        adminActionError.value = extractErrorMessage(error);
    } finally {
        adminActionLoading.value = false;
    }
}

async function loadRestaurants() {
    restaurantsLoading.value = true;
    restaurantsError.value = '';

    try {
        restaurants.value = await fetchRestaurants();
    } catch (error) {
        restaurantsError.value = extractErrorMessage(error);
    } finally {
        restaurantsLoading.value = false;
    }
}

async function loadCart() {
    cartLoading.value = true;
    cartError.value = '';

    try {
        cart.value = await fetchCart();
    } catch (error) {
        cartError.value = extractErrorMessage(error);
    } finally {
        cartLoading.value = false;
    }
}

async function openRestaurant(restaurant) {
    selectedRestaurant.value = restaurant;
    currentView.value = VIEWS.menu;
    menu.value = null;
    menuLoading.value = true;
    menuError.value = '';

    try {
        menu.value = await fetchMenu(restaurant.id);
    } catch (error) {
        menuError.value = extractErrorMessage(error);
    } finally {
        menuLoading.value = false;
    }
}

async function handleAddToCart(dish) {
    addingDishId.value = dish.id;

    try {
        cart.value = await addToCart(dish.id, 1);
    } catch (error) {
        menuError.value = extractErrorMessage(error);
    } finally {
        addingDishId.value = null;
    }
}

async function handleUpdateQuantity(item, quantity) {
    updatingItemId.value = item.id;

    try {
        cart.value = await updateCartItem(item.id, quantity);
    } catch (error) {
        cartError.value = extractErrorMessage(error);
    } finally {
        updatingItemId.value = null;
    }
}

async function handleRemoveItem(item) {
    updatingItemId.value = item.id;

    try {
        cart.value = await removeCartItem(item.id);
    } catch (error) {
        cartError.value = extractErrorMessage(error);
    } finally {
        updatingItemId.value = null;
    }
}

async function handleClearCart() {
    clearingCart.value = true;
    cartError.value = '';

    try {
        cart.value = await clearCart();
    } catch (error) {
        cartError.value = extractErrorMessage(error);
    } finally {
        clearingCart.value = false;
    }
}

async function saveDeliveryAddress(address) {
    const trimmed = address.trim();

    if (trimmed === '') {
        cartError.value = '';
        return;
    }

    savingAddress.value = true;
    cartError.value = '';

    try {
        cart.value = await updateCartDeliveryAddress(trimmed);
    } catch (error) {
        cartError.value = extractErrorMessage(error);
    } finally {
        savingAddress.value = false;
    }
}

/** Отложенное сохранение адреса при вводе (не блокирует UI на каждый символ) */
function handleDeliveryAddressInput(address) {
    if (addressDebounceTimer !== null) {
        clearTimeout(addressDebounceTimer);
    }

    addressDebounceTimer = setTimeout(() => {
        addressDebounceTimer = null;
        saveDeliveryAddress(address);
    }, 500);
}

function handleDeliveryAddressBlur(address) {
    if (addressDebounceTimer !== null) {
        clearTimeout(addressDebounceTimer);
        addressDebounceTimer = null;
    }

    saveDeliveryAddress(address);
}

async function handleSubmitOrder(deliveryAddress) {
    if (addressDebounceTimer !== null) {
        clearTimeout(addressDebounceTimer);
        addressDebounceTimer = null;
    }

    const trimmed = deliveryAddress.trim();

    if (trimmed === '') {
        cartError.value = 'Укажите адрес доставки.';
        return;
    }

    submitting.value = true;
    cartError.value = '';

    try {
        cart.value = await updateCartDeliveryAddress(trimmed);
        submittedOrder.value = await submitOrder();
        cart.value = null;
        currentView.value = VIEWS.confirmation;
    } catch (error) {
        cartError.value = extractErrorMessage(error);
    } finally {
        submitting.value = false;
    }
}

function goToRestaurants() {
    currentView.value = VIEWS.restaurants;
    selectedRestaurant.value = null;
    menu.value = null;
    submittedOrder.value = null;
    selectedOrderId.value = null;
    orderDetail.value = null;
    loadMyOrders({ silent: true });
}

/**
 * Deep link из уведомления MAX: ?order_id=N&view=chat открывает чат заказа.
 *
 * @returns {number|null}
 */
function parseDeepLinkOrderId() {
    const params = new URLSearchParams(window.location.search);
    const orderId = params.get('order_id');
    const view = params.get('view');

    if (!orderId || view !== 'chat') {
        return null;
    }

    const parsed = Number.parseInt(orderId, 10);

    return Number.isNaN(parsed) ? null : parsed;
}

function handleChatMessagesRead() {
    if (hasAdminRoles.value && adminView.value === ADMIN_VIEWS.detail) {
        loadAdminOrders({ silent: true });
    } else if (currentView.value === VIEWS.orderDetail) {
        loadMyOrders({ silent: true });
    }
}

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

function goToOrderFromConfirmation() {
    if (!submittedOrder.value) {
        return;
    }

    openOrderDetail(submittedOrder.value.id);
}

function goToCart() {
    currentView.value = VIEWS.cart;
    loadCart().then(() => {
        syncSelectedRestaurantFromCart();
    });
}

function syncSelectedRestaurantFromCart() {
    if (!cart.value?.restaurant_id || restaurants.value.length === 0) {
        return;
    }

    const restaurant = restaurants.value.find((item) => item.id === cart.value.restaurant_id);

    if (restaurant) {
        selectedRestaurant.value = restaurant;
    }
}

async function goToMenuFromCart() {
    syncSelectedRestaurantFromCart();

    if (!selectedRestaurant.value) {
        goToRestaurants();

        return;
    }

    currentView.value = VIEWS.menu;

    if (menu.value?.restaurant_id === selectedRestaurant.value.id) {
        return;
    }

    menuLoading.value = true;
    menuError.value = '';

    try {
        menu.value = await fetchMenu(selectedRestaurant.value.id);
    } catch (error) {
        menuError.value = extractErrorMessage(error);
    } finally {
        menuLoading.value = false;
    }
}

/**
 * Обработка системной кнопки «Назад» MAX.
 * Учитывает вложенные модалки (корзина) и разные стеки admin / client.
 */
function handleBack() {
    if (hasAdminRoles.value) {
        if (adminView.value === ADMIN_VIEWS.detail) {
            closeAdminOrderDetail();
        }

        return;
    }

    if (currentView.value === VIEWS.cart && cartPageRef.value?.handleBackRequest?.()) {
        return;
    }

    if (currentView.value === VIEWS.menu) {
        goToRestaurants();
        return;
    }

    if (currentView.value === VIEWS.orderDetail) {
        closeOrderDetail();
        return;
    }

    if (currentView.value === VIEWS.orderList) {
        goToRestaurants();
        return;
    }

    if (currentView.value === VIEWS.cart) {
        goToMenuFromCart();
    }
}

/**
 * Привязка BackButton MAX к закрытию приложения или навигации назад.
 * На desktop на корневых экранах «Назад» закрывает mini-app.
 */
function setupBackButton() {
    unbindBackButton();

    if (hasAdminRoles.value) {
        if (adminView.value === ADMIN_VIEWS.list && getPlatform() === 'desktop') {
            unbindBackButton = bindBackButton(closeMaxApp);

            return;
        }

        if (adminView.value === ADMIN_VIEWS.list) {
            hideBackButton();

            return;
        }

        unbindBackButton = bindBackButton(handleBack);

        return;
    }

    if (currentView.value === VIEWS.restaurants && getPlatform() === 'desktop') {
        unbindBackButton = bindBackButton(closeMaxApp);

        return;
    }

    if (currentView.value === VIEWS.restaurants || currentView.value === VIEWS.confirmation) {
        hideBackButton();

        return;
    }

    if (currentView.value === VIEWS.orderList && getPlatform() === 'desktop') {
        unbindBackButton = bindBackButton(closeMaxApp);

        return;
    }

    if (currentView.value === VIEWS.orderList) {
        hideBackButton();

        return;
    }

    unbindBackButton = bindBackButton(handleBack);
}

watch(currentView, setupBackButton);
watch(adminView, setupBackButton);

/** Стартовая последовательность: auth → admin или клиентские данные + deep link */
async function bootstrapApp() {
    await initAuth();

    if (!authError.value) {
        if (hasAdminRoles.value) {
            initAdminSession();
        } else {
            await Promise.all([loadRestaurants(), loadCart(), loadMyOrders({ silent: true })]);

            const deepLinkOrderId = parseDeepLinkOrderId();

            if (deepLinkOrderId !== null) {
                await openOrderDetail(deepLinkOrderId);
            }
        }
    }

    setupBackButton();
}

onMounted(async () => {
    await bootstrapApp();
});

onUnmounted(() => {
    if (addressDebounceTimer !== null) {
        clearTimeout(addressDebounceTimer);
    }

    unbindBackButton();
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
                    @back="closeAdminOrderDetail"
                    @approve="handleAdminApprove"
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
                @go-back="handleBack"
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
                @go-to-order="goToOrderFromConfirmation"
            />
            </template>
        </template>
    </div>
</template>
