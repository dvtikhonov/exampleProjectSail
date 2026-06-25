/**
 * Клиентская навигация без vue-router: currentView и переходы между экранами.
 */
import { VIEWS } from '../constants/views';

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

        const deepLinkOrderId = parseDeepLinkOrderId();

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
