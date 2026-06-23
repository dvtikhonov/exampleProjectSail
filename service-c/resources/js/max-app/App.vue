<script setup>
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import {
    addToCart,
    authenticate,
    clearCart,
    extractErrorMessage,
    fetchCart,
    fetchMenu,
    fetchRestaurants,
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
import RestaurantList from './pages/RestaurantList.vue';

const VIEWS = {
    restaurants: 'restaurants',
    menu: 'menu',
    cart: 'cart',
    confirmation: 'confirmation',
};

const currentView = ref(VIEWS.restaurants);
const authLoading = ref(true);
const authError = ref('');

const restaurants = ref([]);
const restaurantsLoading = ref(false);
const restaurantsError = ref('');

const selectedRestaurant = ref(null);
const menu = ref(null);
const menuLoading = ref(false);
const menuError = ref('');
const addingDishId = ref(null);

const cart = ref(null);
const cartLoading = ref(false);
const cartError = ref('');
const updatingItemId = ref(null);
const clearingCart = ref(false);
const submitting = ref(false);
const savingAddress = ref(false);

const submittedOrder = ref(null);
const cartPageRef = ref(null);

let unbindBackButton = () => {};
let addressDebounceTimer = null;

const cartItemCount = computed(() => {
    if (!cart.value?.items) {
        return 0;
    }

    return cart.value.items.reduce((sum, item) => sum + item.quantity, 0);
});

const cartTotal = computed(() => cart.value?.total ?? '0.00');

async function initAuth() {
    authLoading.value = true;
    authError.value = '';

    try {
        const initData = getInitData();

        if (!initData) {
            throw new Error('Не удалось получить initData от MAX. Откройте приложение через MAX.');
        }

        await authenticate(initData);
    } catch (error) {
        authError.value = extractErrorMessage(error);
    } finally {
        authLoading.value = false;
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

function handleBack() {
    if (currentView.value === VIEWS.cart && cartPageRef.value?.handleBackRequest?.()) {
        return;
    }

    if (currentView.value === VIEWS.menu) {
        goToRestaurants();
        return;
    }

    if (currentView.value === VIEWS.cart) {
        goToMenuFromCart();
    }
}

function setupBackButton() {
    unbindBackButton();

    if (currentView.value === VIEWS.restaurants && getPlatform() === 'desktop') {
        unbindBackButton = bindBackButton(closeMaxApp);

        return;
    }

    if (currentView.value === VIEWS.restaurants || currentView.value === VIEWS.confirmation) {
        hideBackButton();

        return;
    }

    unbindBackButton = bindBackButton(handleBack);
}

watch(currentView, setupBackButton);

async function bootstrapApp() {
    await initAuth();

    if (!authError.value) {
        await Promise.all([loadRestaurants(), loadCart()]);
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
            <RestaurantList
                v-if="currentView === VIEWS.restaurants"
                :restaurants="restaurants"
                :loading="restaurantsLoading"
                :error="restaurantsError"
                :cart-item-count="cartItemCount"
                @select-restaurant="openRestaurant"
                @open-cart="goToCart"
            />

            <MenuPage
                v-else-if="currentView === VIEWS.menu"
                :menu="menu"
                :loading="menuLoading"
                :error="menuError"
                :adding-dish-id="addingDishId"
                :cart-item-count="cartItemCount"
                :cart-total="cartTotal"
                @add-to-cart="handleAddToCart"
                @open-cart="goToCart"
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
                @update-quantity="handleUpdateQuantity"
                @remove-item="handleRemoveItem"
                @clear-cart="handleClearCart"
                @submit-order="handleSubmitOrder"
                @go-back="handleBack"
                @go-to-restaurants="goToRestaurants"
                @delivery-address-input="handleDeliveryAddressInput"
                @delivery-address-blur="handleDeliveryAddressBlur"
            />

            <OrderConfirmationPage
                v-else-if="currentView === VIEWS.confirmation && submittedOrder"
                :order="submittedOrder"
                @back-to-restaurants="goToRestaurants"
            />
        </template>
    </div>
</template>
