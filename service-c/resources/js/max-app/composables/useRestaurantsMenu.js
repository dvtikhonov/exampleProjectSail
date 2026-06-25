/**
 * Клиентский поток: список ресторанов, меню, добавление в корзину.
 */
import { ref } from 'vue';
import { addToCart, extractErrorMessage, fetchMenu, fetchRestaurants } from '../api/foodClient';
import { VIEWS } from '../constants/views';

/**
 * @param {object} deps
 * @param {import('vue').Ref<string>} deps.currentView — текущий экран клиента
 * @param {import('vue').Ref<object|null>} deps.cart — корзина (обновляется при addToCart)
 */
export function useRestaurantsMenu({ currentView, cart }) {
    const restaurants = ref([]);
    const restaurantsLoading = ref(false);
    const restaurantsError = ref('');

    const selectedRestaurant = ref(null);
    const menu = ref(null);
    const menuLoading = ref(false);
    const menuError = ref('');
    const addingDishId = ref(null);

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

    /** Синхронизирует выбранный ресторан с restaurant_id из корзины */
    function syncSelectedRestaurantFromCart() {
        if (!cart.value?.restaurant_id || restaurants.value.length === 0) {
            return;
        }

        const restaurant = restaurants.value.find((item) => item.id === cart.value.restaurant_id);

        if (restaurant) {
            selectedRestaurant.value = restaurant;
        }
    }

    /**
     * Загружает меню ресторана, если ещё не загружено для текущего выбора.
     */
    async function loadMenuForSelectedRestaurant() {
        if (!selectedRestaurant.value) {
            return;
        }

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

    /** Сброс выбранного ресторана и меню при возврате на главный экран */
    function resetRestaurantSelection() {
        selectedRestaurant.value = null;
        menu.value = null;
    }

    return {
        restaurants,
        restaurantsLoading,
        restaurantsError,
        selectedRestaurant,
        menu,
        menuLoading,
        menuError,
        addingDishId,
        loadRestaurants,
        openRestaurant,
        handleAddToCart,
        syncSelectedRestaurantFromCart,
        loadMenuForSelectedRestaurant,
        resetRestaurantSelection,
    };
}
