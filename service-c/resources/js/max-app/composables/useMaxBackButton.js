/**
 * Интеграция с кнопкой «Назад» MAX Bridge: навигация и закрытие mini-app.
 *
 * Зависимости опциональны: каждый shell/root передаёт только свой context
 * (client / orders / manual / menu), без полного набора refs из App.vue.
 */
import { onScopeDispose, watch } from 'vue';
import { bindBackButton, closeMaxApp, getPlatform, hideBackButton } from '../bridge/maxBridge';
import { ADMIN_DISH_VIEWS, ADMIN_SECTIONS, ADMIN_VIEWS, VIEWS } from '../constants/views';

/**
 * @param {object} deps
 * @param {import('vue').ComputedRef<boolean>} deps.hasAdminRoles
 * @param {import('vue').Ref<string>|import('vue').ComputedRef<string>} deps.adminSection
 * @param {import('vue').ComputedRef<boolean>} deps.hasMenuManagerRole
 * @param {import('vue').ComputedRef<boolean>|null} [deps.hasMaxManagerRole]
 * @param {ReturnType<import('./useAdminFlow').useAdminFlow>|null} [deps.admin]
 * @param {ReturnType<import('./useDishAdmin').useDishAdmin>|null} [deps.dishAdmin]
 * @param {ReturnType<import('./useMenuCategoryAdmin').useMenuCategoryAdmin}|null} [deps.categoryAdmin]
 * @param {ReturnType<import('./useManualOrder').useManualOrder}|null} [deps.manualOrder]
 * @param {(() => void)|null} [deps.onManualExitOrdering]
 * @param {ReturnType<import('./useClientNavigation').useClientNavigation}|null} [deps.nav]
 * @param {ReturnType<import('./useCart').useCart}|null} [deps.cart]
 * @param {ReturnType<import('./useMyOrders').useMyOrders}|null} [deps.orders]
 * @param {import('vue').ComputedRef<boolean>|null} [deps.isSingleRestaurantMode]
 */
export function useMaxBackButton({
    hasAdminRoles,
    adminSection,
    hasMenuManagerRole,
    hasMaxManagerRole = null,
    admin = null,
    dishAdmin = null,
    categoryAdmin = null,
    manualOrder = null,
    onManualExitOrdering = null,
    nav = null,
    cart = null,
    orders = null,
    isSingleRestaurantMode = null,
}) {
    /** Снимает обработчик кнопки «Назад» при смене экрана */
    let unbindBackButton = () => {};

    function isMaxManagerSection() {
        return Boolean(
            hasMaxManagerRole?.value
            && adminSection.value === ADMIN_SECTIONS.manualOrders,
        );
    }

    function isManualOrdering() {
        return isMaxManagerSection() && Boolean(manualOrder?.isOrdering?.value);
    }

    /** Список ресторанов (multi) или меню единственного ресторана (single) */
    function isClientRootView() {
        if (!nav?.currentView) {
            return false;
        }

        return nav.currentView.value === VIEWS.restaurants
            || (nav.currentView.value === VIEWS.menu && Boolean(isSingleRestaurantMode?.value));
    }

    function isManualOrderRootView() {
        if (!isMaxManagerSection()) {
            return false;
        }

        if (manualOrder?.isOrderDetailOpen?.value) {
            return false;
        }

        if (!manualOrder?.isOrdering?.value) {
            return true;
        }

        return false;
    }

    /**
     * Обработка системной кнопки «Назад» MAX.
     * Учитывает вложенные модалки (корзина) и разные стеки admin / client.
     */
    function handleBack() {
        if (hasAdminRoles.value) {
            if (isMaxManagerSection() && manualOrder?.isOrderDetailOpen?.value) {
                manualOrder.closeOrderDetail();

                return;
            }

            if (isManualOrdering() && nav && cart) {
                if (nav.currentView.value === VIEWS.cart && cart.cartPageRef.value?.handleBackRequest?.()) {
                    return;
                }

                if (nav.currentView.value === VIEWS.confirmation) {
                    onManualExitOrdering?.();

                    return;
                }

                if (nav.currentView.value === VIEWS.menu) {
                    if (isSingleRestaurantMode?.value) {
                        onManualExitOrdering?.();

                        return;
                    }

                    nav.goToRestaurants();

                    return;
                }

                if (nav.currentView.value === VIEWS.cart) {
                    nav.goToMenuFromCart();

                    return;
                }

                if (nav.currentView.value === VIEWS.restaurants) {
                    onManualExitOrdering?.();
                }

                return;
            }

            if (adminSection.value === ADMIN_SECTIONS.menu && hasMenuManagerRole.value && dishAdmin && categoryAdmin) {
                if (dishAdmin.dishAdminView.value === ADMIN_DISH_VIEWS.form) {
                    dishAdmin.closeDishForm();

                    return;
                }

                if (dishAdmin.dishAdminView.value === ADMIN_DISH_VIEWS.categoryForm) {
                    categoryAdmin.closeCategoryForm(dishAdmin.dishAdminView);

                    return;
                }

                return;
            }

            if (admin?.adminView?.value === ADMIN_VIEWS.detail) {
                admin.closeAdminOrderDetail();
            }

            return;
        }

        if (!nav || !cart) {
            return;
        }

        if (nav.currentView.value === VIEWS.cart && cart.cartPageRef.value?.handleBackRequest?.()) {
            return;
        }

        if (nav.currentView.value === VIEWS.menu) {
            if (isSingleRestaurantMode?.value) {
                if (getPlatform() === 'desktop') {
                    closeMaxApp();
                }

                return;
            }

            nav.goHome();
            return;
        }

        if (nav.currentView.value === VIEWS.orderDetail) {
            orders?.closeOrderDetail?.();
            return;
        }

        if (nav.currentView.value === VIEWS.orderList) {
            nav.goHome();
            return;
        }

        if (nav.currentView.value === VIEWS.cart) {
            nav.goToMenuFromCart();
        }
    }

    /**
     * Привязка BackButton MAX к закрытию приложения или навигации назад.
     * На desktop на корневых экранах «Назад» закрывает mini-app.
     */
    function setupBackButton() {
        unbindBackButton();

        if (hasAdminRoles.value) {
            if (isMaxManagerSection()) {
                if (isManualOrderRootView() && getPlatform() === 'desktop') {
                    unbindBackButton = bindBackButton(closeMaxApp);

                    return;
                }

                if (isManualOrderRootView()) {
                    hideBackButton();

                    return;
                }

                if (nav?.currentView?.value === VIEWS.confirmation) {
                    hideBackButton();

                    return;
                }

                unbindBackButton = bindBackButton(handleBack);

                return;
            }

            if (adminSection.value === ADMIN_SECTIONS.menu && hasMenuManagerRole.value && dishAdmin) {
                if (
                    (dishAdmin.dishAdminView.value === ADMIN_DISH_VIEWS.list
                        || dishAdmin.dishAdminView.value === ADMIN_DISH_VIEWS.schedule
                        || dishAdmin.dishAdminView.value === ADMIN_DISH_VIEWS.categoryList)
                    && getPlatform() === 'desktop'
                ) {
                    unbindBackButton = bindBackButton(closeMaxApp);

                    return;
                }

                if (
                    dishAdmin.dishAdminView.value === ADMIN_DISH_VIEWS.list
                    || dishAdmin.dishAdminView.value === ADMIN_DISH_VIEWS.schedule
                    || dishAdmin.dishAdminView.value === ADMIN_DISH_VIEWS.categoryList
                ) {
                    hideBackButton();

                    return;
                }

                unbindBackButton = bindBackButton(handleBack);

                return;
            }

            if (admin?.adminView?.value === ADMIN_VIEWS.list && getPlatform() === 'desktop') {
                unbindBackButton = bindBackButton(closeMaxApp);

                return;
            }

            if (admin?.adminView?.value === ADMIN_VIEWS.list) {
                hideBackButton();

                return;
            }

            unbindBackButton = bindBackButton(handleBack);

            return;
        }

        if (!nav) {
            return;
        }

        if (isClientRootView() && getPlatform() === 'desktop') {
            unbindBackButton = bindBackButton(closeMaxApp);

            return;
        }

        if (isClientRootView() || nav.currentView.value === VIEWS.confirmation) {
            hideBackButton();

            return;
        }

        if (nav.currentView.value === VIEWS.orderList && getPlatform() === 'desktop') {
            unbindBackButton = bindBackButton(closeMaxApp);

            return;
        }

        if (nav.currentView.value === VIEWS.orderList) {
            hideBackButton();

            return;
        }

        unbindBackButton = bindBackButton(handleBack);
    }

    if (nav?.currentView) {
        watch(nav.currentView, setupBackButton);
    }

    if (admin?.adminView) {
        watch(admin.adminView, setupBackButton);
    }

    watch(adminSection, setupBackButton);

    if (dishAdmin?.dishAdminView) {
        watch(dishAdmin.dishAdminView, setupBackButton);
    }

    if (isSingleRestaurantMode) {
        watch(isSingleRestaurantMode, setupBackButton);
    }

    if (manualOrder?.isOrdering) {
        watch(manualOrder.isOrdering, setupBackButton);
    }

    if (manualOrder?.isOrderDetailOpen) {
        watch(manualOrder.isOrderDetailOpen, setupBackButton);
    }

    function cleanup() {
        unbindBackButton();
    }

    onScopeDispose(cleanup);

    return {
        handleBack,
        setupBackButton,
        cleanup,
    };
}

/**
 * Обновляет счётчики непрочитанных после прочтения чата.
 *
 * @param {object} deps
 * @param {import('vue').ComputedRef<boolean>} deps.hasAdminRoles
 * @param {ReturnType<import('./useAdminFlow').useAdminFlow}|null} [deps.admin]
 * @param {ReturnType<import('./useClientNavigation').useClientNavigation}|null} [deps.nav]
 * @param {ReturnType<import('./useMyOrders').useMyOrders}|null} [deps.orders]
 */
export function createChatMessagesReadHandler({ hasAdminRoles, admin = null, nav = null, orders = null }) {
    return function handleChatMessagesRead() {
        if (hasAdminRoles.value && admin?.adminView?.value === ADMIN_VIEWS.detail) {
            admin.loadAdminOrders({ silent: true });
        } else if (nav?.currentView?.value === VIEWS.orderDetail) {
            orders?.loadMyOrders?.({ silent: true });
        }
    };
}
