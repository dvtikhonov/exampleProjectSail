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

    function goToRestaurants() {
        currentView.value = VIEWS.restaurants;
        restaurantsMenu.resetRestaurantSelection();
        cart.resetSubmittedOrder();
        orders.resetOrderSelection();
        orders.loadMyOrders({ silent: true });
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
            goToRestaurants();

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
        }
    }

    return {
        currentView,
        goToRestaurants,
        goToCart,
        goToMenuFromCart,
        bootstrapClient,
    };
}
