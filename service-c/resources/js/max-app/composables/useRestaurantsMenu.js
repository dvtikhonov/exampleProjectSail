/**
 * Клиентский поток: список ресторанов, меню, добавление в корзину.
 * При getTargetMaxUserId() → number добавляет позиции через manual-orders API.
 */
import { computed, ref } from 'vue';
import {
    addComboToCart,
    addComboToManualCart,
    addToCart,
    addToManualCart,
    extractErrorMessage,
    fetchMenu,
    fetchRestaurants,
} from '../api/foodClient';
import { VIEWS } from '../constants/views';

/**
 * @param {object} deps
 * @param {import('vue').Ref<string>} deps.currentView — текущий экран клиента
 * @param {import('vue').Ref<object|null>} deps.cart — корзина (обновляется при addToCart)
 * @param {(() => number|null)=} deps.getTargetMaxUserId — клиент ручного заказа или null
 */
export function useRestaurantsMenu({ currentView, cart, getTargetMaxUserId = () => null }) {
    const restaurants = ref([]);
    const restaurantsLoading = ref(false);
    const restaurantsError = ref('');

    /** Режим одного доступного ресторана: список не показывается, «домой» ведёт в меню */
    const isSingleRestaurantMode = computed(() => restaurants.value.length === 1);

    const selectedRestaurant = ref(null);
    const menu = ref(null);
    const menuLoading = ref(false);
    const menuError = ref('');
    const addingDishId = ref(null);
    const addingComboRef = ref(null);

    /**
     * @returns {number|null}
     */
    function resolveManualUserId() {
        const id = getTargetMaxUserId();

        return typeof id === 'number' && id > 0 ? id : null;
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
        const manualUserId = resolveManualUserId();

        try {
            cart.value = manualUserId !== null
                ? await addToManualCart(manualUserId, dish.id, 1)
                : await addToCart(dish.id, 1);
        } catch (error) {
            menuError.value = extractErrorMessage(error);
        } finally {
            addingDishId.value = null;
        }
    }

    async function handleAddComboToCart(combo) {
        addingComboRef.value = combo.comboRef;
        menuError.value = '';
        const manualUserId = resolveManualUserId();

        try {
            cart.value = manualUserId !== null
                ? await addComboToManualCart(
                    manualUserId,
                    combo.firstDish.id,
                    combo.secondDish.id,
                    combo.quantity,
                    combo.comboRef,
                )
                : await addComboToCart(
                    combo.firstDish.id,
                    combo.secondDish.id,
                    combo.quantity,
                    combo.comboRef,
                );
        } catch (error) {
            menuError.value = extractErrorMessage(error);
        } finally {
            addingComboRef.value = null;
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
        isSingleRestaurantMode,
        selectedRestaurant,
        menu,
        menuLoading,
        menuError,
        addingDishId,
        addingComboRef,
        loadRestaurants,
        openRestaurant,
        handleAddToCart,
        handleAddComboToCart,
        syncSelectedRestaurantFromCart,
        loadMenuForSelectedRestaurant,
        resetRestaurantSelection,
    };
}
