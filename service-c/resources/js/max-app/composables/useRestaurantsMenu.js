/**
 * Клиентский поток: список ресторанов, меню, добавление в корзину.
 * Добавление в корзину — через CartTransport (client или manual).
 */
import { computed, ref } from 'vue';
import { createClientCartTransport } from '../api/cartTransport';
import { extractErrorMessage, fetchMenu, fetchRestaurants } from '../api';
import { VIEWS } from '../constants/views';

/**
 * @param {object} deps
 * @param {import('vue').Ref<string>} deps.currentView — текущий экран клиента
 * @param {import('vue').Ref<object|null>} deps.cart — корзина (обновляется при addItem)
 * @param {import('../api/cartTransport').CartTransport=} deps.cartTransport — client или manual
 */
export function useRestaurantsMenu({
    currentView,
    cart,
    cartTransport = createClientCartTransport(),
}) {
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
     * Защита от race-condition: применяем только самый новый ответ fetchMenu.
     * @type {number}
     */
    let loadRequestSeq = 0;

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
        const requestSeq = ++loadRequestSeq;

        selectedRestaurant.value = restaurant;
        currentView.value = VIEWS.menu;
        menu.value = null;
        menuLoading.value = true;
        menuError.value = '';

        try {
            const nextMenu = await fetchMenu(restaurant.id, {
                includeUnavailable: cartTransport.includeUnavailableInMenu,
            });

            if (requestSeq !== loadRequestSeq) {
                return;
            }

            menu.value = nextMenu;
        } catch (error) {
            if (requestSeq !== loadRequestSeq) {
                return;
            }

            menuError.value = extractErrorMessage(error);
        } finally {
            if (requestSeq === loadRequestSeq) {
                menuLoading.value = false;
            }
        }
    }

    async function handleAddToCart(dish) {
        addingDishId.value = dish.id;

        try {
            cart.value = await cartTransport.addItem(dish.id, 1);
        } catch (error) {
            menuError.value = extractErrorMessage(error);
        } finally {
            addingDishId.value = null;
        }
    }

    async function handleAddComboToCart(combo) {
        addingComboRef.value = combo.comboRef;
        menuError.value = '';

        try {
            cart.value = await cartTransport.addCombo(
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

        const requestSeq = ++loadRequestSeq;
        const restaurantId = selectedRestaurant.value.id;

        menuLoading.value = true;
        menuError.value = '';

        try {
            const nextMenu = await fetchMenu(restaurantId, {
                includeUnavailable: cartTransport.includeUnavailableInMenu,
            });

            if (requestSeq !== loadRequestSeq) {
                return;
            }

            menu.value = nextMenu;
        } catch (error) {
            if (requestSeq !== loadRequestSeq) {
                return;
            }

            menuError.value = extractErrorMessage(error);
        } finally {
            if (requestSeq === loadRequestSeq) {
                menuLoading.value = false;
            }
        }
    }

    /** Сброс выбранного ресторана и меню при возврате на главный экран */
    function resetRestaurantSelection() {
        loadRequestSeq += 1;
        selectedRestaurant.value = null;
        menu.value = null;
        menuLoading.value = false;
        menuError.value = '';
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
