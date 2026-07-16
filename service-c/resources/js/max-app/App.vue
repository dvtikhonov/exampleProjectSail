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
import { disableVerticalSwipes, getStartParam } from './bridge/maxBridge';
import { useAdminFlow } from './composables/useAdminFlow';
import { useAuth } from './composables/useAuth';
import { useCart } from './composables/useCart';
import { useClientNavigation } from './composables/useClientNavigation';
import { useDishAdmin } from './composables/useDishAdmin';
import { useDishAdminFilters } from './composables/useDishAdminFilters';
import { useDishAvailabilitySchedule } from './composables/useDishAvailabilitySchedule';
import { useMenuCategoryAdmin } from './composables/useMenuCategoryAdmin';
import { createChatMessagesReadHandler, useMaxBackButton } from './composables/useMaxBackButton';
import { useMyOrders } from './composables/useMyOrders';
import { useRestaurantsMenu } from './composables/useRestaurantsMenu';
import { ADMIN_DISH_VIEWS, ADMIN_SECTIONS, ADMIN_VIEWS, VIEWS } from './constants/views';
import { resolveOrderChatDeepLinkOrderId } from './utils/orderChatDeepLink';
import CartPage from './pages/CartPage.vue';
import MenuPage from './pages/MenuPage.vue';
import OrderConfirmationPage from './pages/OrderConfirmationPage.vue';
import OrderDetailPage from './pages/OrderDetailPage.vue';
import OrderListPage from './pages/OrderListPage.vue';
import RestaurantList from './pages/RestaurantList.vue';
import AdminDishFormPage from './pages/admin/AdminDishFormPage.vue';
import AdminDishAvailabilityPage from './pages/admin/AdminDishAvailabilityPage.vue';
import AdminDishListPage from './pages/admin/AdminDishListPage.vue';
import AdminMenuCategoryFormPage from './pages/admin/AdminMenuCategoryFormPage.vue';
import AdminMenuCategoryListPage from './pages/admin/AdminMenuCategoryListPage.vue';
import AdminHomePage from './pages/admin/AdminHomePage.vue';
import AdminOrderDetailPage from './pages/admin/AdminOrderDetailPage.vue';

/** Текущий экран клиентского потока — общий ref для cart, orders, navigation */
const currentView = ref(VIEWS.restaurants);

const {
    authLoading,
    authError,
    adminRoles,
    adminSection,
    hasOrderReviewRoles,
    hasMenuManagerRole,
    hasAdminRoles,
    showAdminSectionSwitcher,
    adminScope,
    initAuth,
} = useAuth();

const admin = useAdminFlow(adminScope);
const dishFilters = useDishAdminFilters();
const dishAdmin = useDishAdmin({ filters: dishFilters });
const categoryAdmin = useMenuCategoryAdmin({
    filters: dishFilters,
    onCategoriesChanged: () => dishAdmin.loadCategories(),
});
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
} = admin;

const {
    dishAdminView,
    dishes,
    dishesLoading,
    dishesRefreshing,
    dishesError,
    filterRestaurantId,
    filterCategoryId,
    filterNameSearch,
    formRestaurantId,
    restaurantOptions,
    categoryFilterOptions,
    categoryFormOptions,
    editingDish,
    formLoading,
    formError,
    formFieldErrors,
    deleteLoadingId,
    deleteError,
    importLoading,
    importError,
    importSuccessMessage,
    testBotLoading,
    testBotError,
    testBotSuccessMessage,
    testBot2Loading,
    testBot2Error,
    testBot2SuccessMessage,
    initDishAdminSession,
    loadDishes,
    handleFilterRestaurantChange,
    handleFilterCategoryChange,
    handleFilterNameSearchChange,
    openCreateForm,
    openEditForm,
    openDishListView,
    openDishScheduleView,
    closeDishForm,
    handleFormRestaurantChange,
    submitDishForm,
    handleDeleteDish,
    handleImportClick,
    handleImportFile,
    handleTestBotClick,
    handleTestBot2Click,
} = dishAdmin;

const {
    filteredCategories,
    categoriesLoading,
    categoriesRefreshing,
    categoriesError,
    restaurantOptions: categoryRestaurantOptions,
    editingCategory,
    categoryFormLoading,
    categoryFormError,
    categoryFormFieldErrors,
    categoryDeleteLoadingId,
    categoryDeleteError,
    loadRestaurantOptions,
    loadMenuCategories,
    openCategoryListView,
    openCreateCategoryForm,
    openEditCategoryForm,
    closeCategoryForm,
    submitCategoryForm,
    handleDeleteCategory,
    handleCategoryFilterRestaurantChange,
} = categoryAdmin;

const dishSchedule = useDishAvailabilitySchedule({ categories: dishAdmin.categories, filters: dishFilters });
const {
    categoryOptions: scheduleCategoryOptions,
    filteredDishes: scheduleFilteredDishes,
    dates: scheduleDates,
    editableFrom: scheduleEditableFrom,
    loading: scheduleLoading,
    saving: scheduleSaving,
    error: scheduleError,
    saveError: scheduleSaveError,
    filtersReady: scheduleFiltersReady,
    hasUnsavedChanges: scheduleHasUnsavedChanges,
    loadSchedule,
    handleFilterRestaurantChange: handleScheduleFilterRestaurantChange,
    handleFilterCategoryChange: handleScheduleFilterCategoryChange,
    handleFilterNameSearchChange: handleScheduleFilterNameSearchChange,
    isDateEditable: isScheduleDateEditable,
    isAvailable: isScheduleDateAvailable,
    toggleAvailability: toggleScheduleAvailability,
    saveSchedule,
} = dishSchedule;

const dishListPageRef = ref(null);

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
    addingComboRef,
    openRestaurant,
    handleAddToCart,
    handleAddComboToCart,
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
    adminSection,
    hasMenuManagerRole,
    admin,
    dishAdmin,
    categoryAdmin,
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
        disableVerticalSwipes();

        const deepLinkOrderId = resolveOrderChatDeepLinkOrderId({ getStartParam });

        if (hasAdminRoles.value) {
            if (deepLinkOrderId !== null && hasOrderReviewRoles.value) {
                adminSection.value = ADMIN_SECTIONS.orders;
                initAdminSession();
                await openAdminOrderById(deepLinkOrderId);
            } else if (adminSection.value === ADMIN_SECTIONS.menu && hasMenuManagerRole.value) {
                initDishAdminSession();
            } else if (hasOrderReviewRoles.value) {
                initAdminSession();
            }
        } else {
            await bootstrapClient();
        }
    }

    back.setupBackButton();
}

/**
 * @param {string} section
 */
function handleAdminSectionChange(section) {
    if (adminSection.value === section) {
        return;
    }

    adminSection.value = section;

    if (section === ADMIN_SECTIONS.menu) {
        initDishAdminSession();
    } else {
        initAdminSession();
    }

    back.setupBackButton();
}

/**
 * @param {object} fields
 * @param {File|null} photoFile
 */
function handleDishFormSubmit(fields, photoFile) {
    submitDishForm(fields, photoFile);
}

function onDishImportClick() {
    if (handleImportClick()) {
        dishListPageRef.value?.openFilePicker();
    }
}

/**
 * @param {File} file
 */
function onDishImportFile(file) {
    handleImportFile(file);
}

/**
 * @param {object} fields
 */
function handleCategoryFormSubmit(fields) {
    submitCategoryForm(dishAdminView, fields);
}

function handleCloseCategoryForm() {
    closeCategoryForm(dishAdminView);
}

function handleOpenCreateCategoryForm() {
    openCreateCategoryForm(dishAdminView);
}

/**
 * @param {object} category
 */
function handleOpenEditCategoryForm(category) {
    openEditCategoryForm(dishAdminView, category);
}

/**
 * @param {object} category
 */
function handleDeleteMenuCategory(category) {
    handleDeleteCategory(dishAdminView, category);
}

/**
 * @param {string} value
 */
function handleCategoryFilterRestaurant(value) {
    handleCategoryFilterRestaurantChange(value, dishAdminView);
}

function handleOpenDishListView() {
    if (dishAdminView.value === ADMIN_DISH_VIEWS.list) {
        return;
    }

    openDishListView();
    loadDishes();
    back.setupBackButton();
}

function handleOpenCategoryListView() {
    if (dishAdminView.value === ADMIN_DISH_VIEWS.categoryList) {
        return;
    }

    openCategoryListView(dishAdminView);
    loadRestaurantOptions();
    loadMenuCategories();
    back.setupBackButton();
}

function handleOpenDishScheduleView() {
    if (dishAdminView.value === ADMIN_DISH_VIEWS.schedule) {
        return;
    }

    openDishScheduleView();
    loadSchedule();
    back.setupBackButton();
}

/**
 * @param {number} dishId
 * @param {string} date
 */
function handleScheduleToggle(dishId, date) {
    toggleScheduleAvailability(dishId, date);
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
                <div class="flex h-dvh flex-col overflow-hidden">
                    <header
                        v-if="showAdminSectionSwitcher"
                        class="z-20 shrink-0 border-b border-gray-200 bg-white safe-area-top"
                    >
                        <nav class="flex" aria-label="Разделы админки">
                            <button
                                type="button"
                                class="flex-1 border-b-2 px-4 py-2 text-sm font-medium transition"
                                :class="
                                    adminSection === ADMIN_SECTIONS.orders
                                        ? 'border-max-primary text-max-primary'
                                        : 'border-transparent text-max-muted hover:text-gray-700'
                                "
                                @click="handleAdminSectionChange(ADMIN_SECTIONS.orders)"
                            >
                                Заказы
                            </button>
                            <button
                                type="button"
                                class="flex-1 border-b-2 px-4 py-2 text-sm font-medium transition"
                                :class="
                                    adminSection === ADMIN_SECTIONS.menu
                                        ? 'border-max-primary text-max-primary'
                                        : 'border-transparent text-max-muted hover:text-gray-700'
                                "
                                @click="handleAdminSectionChange(ADMIN_SECTIONS.menu)"
                            >
                                Меню
                            </button>
                        </nav>
                    </header>

                    <div class="flex min-h-0 flex-1 flex-col overflow-hidden">
                        <template v-if="adminSection === ADMIN_SECTIONS.menu && hasMenuManagerRole">
                    <div class="flex min-h-0 flex-1 flex-col overflow-hidden">
                    <nav
                        v-if="dishAdminView !== ADMIN_DISH_VIEWS.form && dishAdminView !== ADMIN_DISH_VIEWS.categoryForm"
                        class="z-10 shrink-0 border-b border-gray-100 bg-white"
                        aria-label="Режим управления меню"
                    >
                        <div class="flex">
                            <button
                                type="button"
                                class="flex-1 border-b-2 px-4 py-2.5 text-sm font-medium transition"
                                :class="
                                    dishAdminView === ADMIN_DISH_VIEWS.list
                                        ? 'border-max-primary text-max-primary'
                                        : 'border-transparent text-max-muted hover:text-gray-700'
                                "
                                @click="handleOpenDishListView"
                            >
                                Блюда
                            </button>
                            <button
                                type="button"
                                class="flex-1 border-b-2 px-4 py-2.5 text-sm font-medium transition"
                                :class="
                                    dishAdminView === ADMIN_DISH_VIEWS.categoryList
                                        || dishAdminView === ADMIN_DISH_VIEWS.categoryForm
                                        ? 'border-max-primary text-max-primary'
                                        : 'border-transparent text-max-muted hover:text-gray-700'
                                "
                                @click="handleOpenCategoryListView"
                            >
                                Категории
                            </button>
                            <button
                                type="button"
                                class="flex-1 border-b-2 px-4 py-2.5 text-sm font-medium transition"
                                :class="
                                    dishAdminView === ADMIN_DISH_VIEWS.schedule
                                        ? 'border-max-primary text-max-primary'
                                        : 'border-transparent text-max-muted hover:text-gray-700'
                                "
                                @click="handleOpenDishScheduleView"
                            >
                                График
                            </button>
                        </div>
                    </nav>

                    <AdminMenuCategoryFormPage
                        v-if="dishAdminView === ADMIN_DISH_VIEWS.categoryForm"
                        :category="editingCategory"
                        :restaurant-options="categoryRestaurantOptions"
                        :loading="categoryFormLoading && !editingCategory"
                        :submit-loading="categoryFormLoading"
                        :error="categoryFormError"
                        :server-field-errors="categoryFormFieldErrors"
                        @back="handleCloseCategoryForm"
                        @submit="handleCategoryFormSubmit"
                    />

                    <KeepAlive>
                        <AdminMenuCategoryListPage
                            v-if="dishAdminView === ADMIN_DISH_VIEWS.categoryList"
                            class="min-h-0 flex-1"
                            :categories="filteredCategories"
                            :loading="categoriesLoading"
                            :error="categoriesError"
                            :refreshing="categoriesRefreshing"
                            :delete-error="categoryDeleteError"
                            :delete-loading-id="categoryDeleteLoadingId"
                            :restaurant-options="categoryRestaurantOptions"
                            :filter-restaurant-id="filterRestaurantId"
                            @add="handleOpenCreateCategoryForm"
                            @edit="handleOpenEditCategoryForm"
                            @delete="handleDeleteMenuCategory"
                            @refresh="loadMenuCategories({ refreshing: true })"
                            @filter-restaurant="handleCategoryFilterRestaurant"
                        />
                    </KeepAlive>

                    <AdminDishFormPage
                        v-if="dishAdminView === ADMIN_DISH_VIEWS.form"
                        :dish="editingDish"
                        :category-options="categoryFormOptions"
                        :restaurant-options="restaurantOptions"
                        :restaurant-id="formRestaurantId"
                        :loading="formLoading && !editingDish"
                        :submit-loading="formLoading"
                        :error="formError"
                        :server-field-errors="formFieldErrors"
                        @back="closeDishForm"
                        @update:restaurant-id="handleFormRestaurantChange"
                        @submit="handleDishFormSubmit"
                    />

                    <KeepAlive>
                        <AdminDishListPage
                            v-if="dishAdminView === ADMIN_DISH_VIEWS.list"
                            ref="dishListPageRef"
                            class="min-h-0 flex-1"
                            :dishes="dishes"
                            :loading="dishesLoading"
                            :error="dishesError"
                            :refreshing="dishesRefreshing"
                            :delete-error="deleteError"
                            :delete-loading-id="deleteLoadingId"
                            :restaurant-options="restaurantOptions"
                            :category-options="categoryFilterOptions"
                            :filter-restaurant-id="filterRestaurantId"
                            :filter-category-id="filterCategoryId"
                            :filter-name-search="filterNameSearch"
                            :import-loading="importLoading"
                            :import-error="importError"
                            :import-success-message="importSuccessMessage"
                            :test-bot-loading="testBotLoading"
                            :test-bot-error="testBotError"
                            :test-bot-success-message="testBotSuccessMessage"
                            :test-bot2-loading="testBot2Loading"
                            :test-bot2-error="testBot2Error"
                            :test-bot2-success-message="testBot2SuccessMessage"
                            @add="openCreateForm"
                            @edit="openEditForm"
                            @delete="handleDeleteDish"
                            @refresh="loadDishes({ refreshing: true })"
                            @filter-restaurant="handleFilterRestaurantChange"
                            @filter-category="handleFilterCategoryChange"
                            @filter-name-search="handleFilterNameSearchChange"
                            @import-click="onDishImportClick"
                            @import="onDishImportFile"
                            @test-bot="handleTestBotClick"
                            @test-bot-2="handleTestBot2Click"
                        />
                        </KeepAlive>

                    <AdminDishAvailabilityPage
                        v-if="dishAdminView === ADMIN_DISH_VIEWS.schedule"
                        class="min-h-0 flex-1"
                        :dishes="scheduleFilteredDishes"
                        :dates="scheduleDates"
                        :editable-from="scheduleEditableFrom"
                        :loading="scheduleLoading"
                        :saving="scheduleSaving"
                        :error="scheduleError"
                        :save-error="scheduleSaveError"
                        :filters-ready="scheduleFiltersReady"
                        :has-unsaved-changes="scheduleHasUnsavedChanges"
                        :restaurant-options="restaurantOptions"
                        :category-options="scheduleCategoryOptions"
                        :filter-restaurant-id="filterRestaurantId"
                        :filter-category-id="filterCategoryId"
                        :filter-name-search="filterNameSearch"
                        :is-date-editable="isScheduleDateEditable"
                        :is-available="isScheduleDateAvailable"
                        @filter-restaurant="handleScheduleFilterRestaurantChange"
                        @filter-category="handleScheduleFilterCategoryChange"
                        @filter-name-search="handleScheduleFilterNameSearchChange"
                        @toggle="handleScheduleToggle"
                        @save="saveSchedule"
                        @refresh="loadSchedule"
                    />
                    </div>
                        </template>

                        <template v-else-if="hasOrderReviewRoles">
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
                    </div>
                </div>
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
                    :delivery-address="cart?.delivery_address ?? ''"
                    :loading="menuLoading"
                    :error="menuError"
                    :adding-dish-id="addingDishId"
                    :adding-combo-ref="addingComboRef"
                    :cart-item-count="cartItemCount"
                    :cart-total="cartTotal"
                    :orders-unread-count="ordersUnreadCount"
                    :saving-address="savingAddress"
                    @add-to-cart="handleAddToCart"
                    @add-combo-to-cart="handleAddComboToCart"
                    @open-cart="goToCart"
                    @open-orders="goToMyOrders"
                    @delivery-address-input="handleDeliveryAddressInput"
                    @delivery-address-blur="handleDeliveryAddressBlur"
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
