/**
 * Клиентская навигация без vue-router: currentView и переходы между экранами.
 */
import { getStartParam } from '../bridge/maxBridge';
import { VIEWS } from '../constants/views';
import { resolveOrderChatDeepLinkOrderId } from '../utils/orderChatDeepLink';

/**
 * @param {object} deps — доменные composables клиентского потока
 * @param {import('vue').Ref<string>} deps.currentView — общий ref экрана (создаётся в App.vue)
 * @param {ReturnType<import('./useRestaurantsMenu').useRestaurantsMenu>} deps.restaurantsMenu
 * @param {ReturnType<import('./useCart').useCart>} deps.cart
 * @param {ReturnType<import('./useMyOrders').useMyOrders>} deps.orders
 */
export function useClientNavigation({ currentView, restaurantsMenu, cart, orders }) {

    /**
     * Список ресторанов (multi mode): сброс выбора и переход на список.
     */
    function goToRestaurants() {
        currentView.value = VIEWS.restaurants;
        restaurantsMenu.resetRestaurantSelection();
        cart.resetSubmittedOrder();
        orders.resetOrderSelection();
        orders.loadMyOrders({ silent: true });
    }

    /**
     * «Домой»: в single-restaurant mode — меню единственного ресторана,
     * иначе — список ресторанов.
     */
    async function goHome() {
        if (restaurantsMenu.isSingleRestaurantMode.value) {
            cart.resetSubmittedOrder();
            orders.resetOrderSelection();
            orders.loadMyOrders({ silent: true });
            await restaurantsMenu.openRestaurant(restaurantsMenu.restaurants.value[0]);

            return;
        }

        goToRestaurants();
    }

    function goToCart() {
        currentView.value = VIEWS.cart;
        cart.loadCart().then(() => {
            restaurantsMenu.syncSelectedRestaurantFromCart();
        });
    }

    async function goToMenuFromCart() {
        restaurantsMenu.syncSelectedRestaurantFromCart();

        if (!restaurantsMenu.selectedRestaurant.value) {
            await goHome();

            return;
        }

        currentView.value = VIEWS.menu;
        await restaurantsMenu.loadMenuForSelectedRestaurant();
    }

    /** Стартовая загрузка клиентских данных и обработка deep link */
    async function bootstrapClient() {
        await Promise.all([
            restaurantsMenu.loadRestaurants(),
            cart.loadCart(),
            orders.loadMyOrders({ silent: true }),
        ]);

        const deepLinkOrderId = resolveOrderChatDeepLinkOrderId({ getStartParam });

        if (deepLinkOrderId !== null) {
            await orders.openOrderDetail(deepLinkOrderId);

            return;
        }

        if (restaurantsMenu.isSingleRestaurantMode.value) {
            await restaurantsMenu.openRestaurant(restaurantsMenu.restaurants.value[0]);
        }
    }

    return {
        currentView,
        goToRestaurants,
        goHome,
        goToCart,
        goToMenuFromCart,
        bootstrapClient,
    };
}
