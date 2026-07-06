/**
 * Интеграция с кнопкой «Назад» MAX Bridge: навигация и закрытие mini-app.
 */
import { onScopeDispose, watch } from 'vue';
import { bindBackButton, closeMaxApp, getPlatform, hideBackButton } from '../bridge/maxBridge';
import { ADMIN_DISH_VIEWS, ADMIN_SECTIONS, ADMIN_VIEWS, VIEWS } from '../constants/views';

/**
 * @param {object} deps
 * @param {import('vue').ComputedRef<boolean>} deps.hasAdminRoles
 * @param {import('vue').Ref<string>} deps.adminSection
 * @param {import('vue').ComputedRef<boolean>} deps.hasMenuManagerRole
 * @param {ReturnType<import('./useAdminFlow').useAdminFlow>} deps.admin
 * @param {ReturnType<import('./useDishAdmin').useDishAdmin>} deps.dishAdmin
 * @param {ReturnType<import('./useClientNavigation').useClientNavigation>} deps.nav
 * @param {ReturnType<import('./useCart').useCart>} deps.cart
 * @param {ReturnType<import('./useMyOrders').useMyOrders>} deps.orders
 */
export function useMaxBackButton({
    hasAdminRoles,
    adminSection,
    hasMenuManagerRole,
    admin,
    dishAdmin,
    nav,
    cart,
    orders,
}) {
    /** Снимает обработчик кнопки «Назад» при смене экрана */
    let unbindBackButton = () => {};

    /**
     * Обработка системной кнопки «Назад» MAX.
     * Учитывает вложенные модалки (корзина) и разные стеки admin / client.
     */
    function handleBack() {
        if (hasAdminRoles.value) {
            if (adminSection.value === ADMIN_SECTIONS.menu && hasMenuManagerRole.value) {
                if (dishAdmin.dishAdminView.value === ADMIN_DISH_VIEWS.form) {
                    dishAdmin.closeDishForm();
                }

                return;
            }

            if (admin.adminView.value === ADMIN_VIEWS.detail) {
                admin.closeAdminOrderDetail();
            }

            return;
        }

        if (nav.currentView.value === VIEWS.cart && cart.cartPageRef.value?.handleBackRequest?.()) {
            return;
        }

        if (nav.currentView.value === VIEWS.menu) {
            nav.goToRestaurants();
            return;
        }

        if (nav.currentView.value === VIEWS.orderDetail) {
            orders.closeOrderDetail();
            return;
        }

        if (nav.currentView.value === VIEWS.orderList) {
            nav.goToRestaurants();
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
            if (adminSection.value === ADMIN_SECTIONS.menu && hasMenuManagerRole.value) {
                if (
                    (dishAdmin.dishAdminView.value === ADMIN_DISH_VIEWS.list
                        || dishAdmin.dishAdminView.value === ADMIN_DISH_VIEWS.schedule)
                    && getPlatform() === 'desktop'
                ) {
                    unbindBackButton = bindBackButton(closeMaxApp);

                    return;
                }

                if (
                    dishAdmin.dishAdminView.value === ADMIN_DISH_VIEWS.list
                    || dishAdmin.dishAdminView.value === ADMIN_DISH_VIEWS.schedule
                ) {
                    hideBackButton();

                    return;
                }

                unbindBackButton = bindBackButton(handleBack);

                return;
            }

            if (admin.adminView.value === ADMIN_VIEWS.list && getPlatform() === 'desktop') {
                unbindBackButton = bindBackButton(closeMaxApp);

                return;
            }

            if (admin.adminView.value === ADMIN_VIEWS.list) {
                hideBackButton();

                return;
            }

            unbindBackButton = bindBackButton(handleBack);

            return;
        }

        if (nav.currentView.value === VIEWS.restaurants && getPlatform() === 'desktop') {
            unbindBackButton = bindBackButton(closeMaxApp);

            return;
        }

        if (nav.currentView.value === VIEWS.restaurants || nav.currentView.value === VIEWS.confirmation) {
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

    watch(nav.currentView, setupBackButton);
    watch(admin.adminView, setupBackButton);
    watch(adminSection, setupBackButton);
    watch(dishAdmin.dishAdminView, setupBackButton);

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
 * @param {ReturnType<import('./useAdminFlow').useAdminFlow>} deps.admin
 * @param {ReturnType<import('./useClientNavigation').useClientNavigation>} deps.nav
 * @param {ReturnType<import('./useMyOrders').useMyOrders>} deps.orders
 */
export function createChatMessagesReadHandler({ hasAdminRoles, admin, nav, orders }) {
    return function handleChatMessagesRead() {
        if (hasAdminRoles.value && admin.adminView.value === ADMIN_VIEWS.detail) {
            admin.loadAdminOrders({ silent: true });
        } else if (nav.currentView.value === VIEWS.orderDetail) {
            orders.loadMyOrders({ silent: true });
        }
    };
}
