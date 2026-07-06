/**
 * График доступности блюд: загрузка, локальные правки и сохранение.
 */
import { computed, ref, watch } from 'vue';
import {
    extractErrorMessage,
    fetchDishAvailabilitySchedule,
    updateDishAvailabilitySchedule,
} from '../api/foodClient';

/**
 * @param {object[]} items
 * @param {number|null} restaurantId
 * @returns {object[]}
 */
function categoriesForRestaurant(items, restaurantId) {
    if (restaurantId === null) {
        return [];
    }

    return items
        .filter((item) => item.restaurant_id === restaurantId)
        .sort((a, b) => a.name.localeCompare(b.name, 'ru'));
}

/**
 * @param {Record<string, string[]>} source
 * @returns {Record<string, string[]>}
 */
function cloneSchedule(source) {
    /** @type {Record<string, string[]>} */
    const result = {};

    for (const [dishId, dates] of Object.entries(source)) {
        result[dishId] = [...dates];
    }

    return result;
}

/**
 * @param {object[]} items
 * @param {string} search
 * @returns {object[]}
 */
function filterDishesByName(items, search) {
    const normalizedSearch = search.trim().toLowerCase();

    if (!normalizedSearch) {
        return items;
    }

    return items.filter((dish) => dish.name.toLowerCase().includes(normalizedSearch));
}

/**
 * @param {{ categories: import('vue').Ref<object[]>, filters: ReturnType<typeof import('./useDishAdminFilters').useDishAdminFilters> }} options
 */
export function useDishAvailabilitySchedule({ categories, filters }) {
    const { filterRestaurantId, filterCategoryId, filterNameSearch } = filters;

    const dishes = ref([]);
    const dates = ref([]);
    const editableFrom = ref('');

    const serverSchedule = ref({});
    const localSchedule = ref({});

    const loading = ref(false);
    const saving = ref(false);
    const error = ref('');
    const saveError = ref('');

    const filtersReady = computed(() => filterRestaurantId.value !== '' && filterCategoryId.value !== '');

    const filteredDishes = computed(() => filterDishesByName(dishes.value, filterNameSearch.value));

    const categoryOptions = computed(() => {
        const restaurantId = filterRestaurantId.value ? Number(filterRestaurantId.value) : null;

        return categoriesForRestaurant(categories.value, restaurantId);
    });

    const hasUnsavedChanges = computed(() => {
        if (!filtersReady.value) {
            return false;
        }

        for (const dish of dishes.value) {
            const dishId = String(dish.id);
            const original = sortDates(filterFutureDates(serverSchedule.value[dishId] ?? [], editableFrom.value));
            const current = sortDates(filterFutureDates(localSchedule.value[dishId] ?? [], editableFrom.value));

            if (!areDateListsEqual(original, current)) {
                return true;
            }
        }

        return false;
    });

    /**
     * @param {string} value
     */
    function handleFilterRestaurantChange(value) {
        filterRestaurantId.value = value;
        filterCategoryId.value = '';
        loadSchedule();
    }

    /**
     * @param {string} value
     */
    function handleFilterCategoryChange(value) {
        filterCategoryId.value = value;
        loadSchedule();
    }

    function resetState() {
        dishes.value = [];
        dates.value = [];
        editableFrom.value = '';
        serverSchedule.value = {};
        localSchedule.value = {};
        error.value = '';
        saveError.value = '';
    }

    async function loadSchedule() {
        if (!filtersReady.value) {
            resetState();

            return;
        }

        loading.value = true;
        error.value = '';
        saveError.value = '';

        try {
            const data = await fetchDishAvailabilitySchedule({
                restaurantId: Number(filterRestaurantId.value),
                categoryId: Number(filterCategoryId.value),
            });

            dishes.value = data.dishes ?? [];
            editableFrom.value = data.editable_from ?? '';
            dates.value = filterVisibleDates(data.dates ?? [], editableFrom.value);
            serverSchedule.value = normalizeScheduleKeys(data.schedule ?? {});
            localSchedule.value = cloneSchedule(serverSchedule.value);
        } catch (loadError) {
            error.value = extractErrorMessage(loadError);
        } finally {
            loading.value = false;
        }
    }

    /**
     * @param {string} value
     */
    function handleFilterNameSearchChange(value) {
        filterNameSearch.value = value;
    }

    /**
     * @param {string} date
     * @returns {boolean}
     */
    function isDateEditable(date) {
        if (!editableFrom.value) {
            return false;
        }

        return date >= editableFrom.value;
    }

    /**
     * @param {number} dishId
     * @param {string} date
     * @returns {boolean}
     */
    function isAvailable(dishId, date) {
        const dishDates = localSchedule.value[String(dishId)] ?? [];

        return dishDates.includes(date);
    }

    /**
     * @param {number} dishId
     * @param {string} date
     */
    function toggleAvailability(dishId, date) {
        if (!isDateEditable(date)) {
            return;
        }

        const dishKey = String(dishId);
        const currentDates = [...(localSchedule.value[dishKey] ?? [])];
        const index = currentDates.indexOf(date);

        if (index === -1) {
            currentDates.push(date);
        } else {
            currentDates.splice(index, 1);
        }

        localSchedule.value = {
            ...localSchedule.value,
            [dishKey]: sortDates(currentDates),
        };
    }

    async function saveSchedule() {
        if (!filtersReady.value || !hasUnsavedChanges.value) {
            return;
        }

        saving.value = true;
        saveError.value = '';

        try {
            const changes = buildChangesPayload(
                dishes.value,
                serverSchedule.value,
                localSchedule.value,
                editableFrom.value,
            );

            await updateDishAvailabilitySchedule({
                restaurantId: Number(filterRestaurantId.value),
                categoryId: Number(filterCategoryId.value),
                changes,
            });

            await loadSchedule();
        } catch (saveErr) {
            saveError.value = extractErrorMessage(saveErr);
        } finally {
            saving.value = false;
        }
    }

    watch(
        () => categories.value.length,
        () => {
            if (filterRestaurantId.value && filterCategoryId.value) {
                const categoryStillExists = categoryOptions.value
                    .some((category) => String(category.id) === filterCategoryId.value);

                if (!categoryStillExists) {
                    filterCategoryId.value = '';
                    resetState();
                }
            }
        },
    );

    return {
        filterRestaurantId,
        filterCategoryId,
        filterNameSearch,
        categoryOptions,
        dishes,
        filteredDishes,
        dates,
        editableFrom,
        loading,
        saving,
        error,
        saveError,
        filtersReady,
        hasUnsavedChanges,
        loadSchedule,
        handleFilterRestaurantChange,
        handleFilterCategoryChange,
        handleFilterNameSearchChange,
        isDateEditable,
        isAvailable,
        toggleAvailability,
        saveSchedule,
    };
}

/**
 * @param {Record<string, string[]>} schedule
 * @returns {Record<string, string[]>}
 */
function normalizeScheduleKeys(schedule) {
    /** @type {Record<string, string[]>} */
    const result = {};

    for (const [dishId, dishDates] of Object.entries(schedule)) {
        result[String(dishId)] = sortDates([...(dishDates ?? [])]);
    }

    return result;
}

/**
 * @param {string[]} items
 * @param {string} editableFrom
 * @returns {string[]}
 */
function filterVisibleDates(items, editableFrom) {
    if (!editableFrom) {
        return [];
    }

    return items.filter((date) => date >= editableFrom);
}

/**
 * @param {string[]} items
 * @param {string} editableFrom
 * @returns {string[]}
 */
function filterFutureDates(items, editableFrom) {
    if (!editableFrom) {
        return [...items];
    }

    return items.filter((date) => date >= editableFrom);
}

/**
 * @param {string[]} items
 * @returns {string[]}
 */
function sortDates(items) {
    return [...items].sort();
}

/**
 * @param {string[]} left
 * @param {string[]} right
 * @returns {boolean}
 */
function areDateListsEqual(left, right) {
    if (left.length !== right.length) {
        return false;
    }

    for (let index = 0; index < left.length; index += 1) {
        if (left[index] !== right[index]) {
            return false;
        }
    }

    return true;
}

/**
 * @param {object[]} dishItems
 * @param {Record<string, string[]>} originalSchedule
 * @param {Record<string, string[]>} editedSchedule
 * @param {string} editableFrom
 * @returns {{ dish_id: number, dates: string[] }[]}
 */
function buildChangesPayload(dishItems, originalSchedule, editedSchedule, editableFrom) {
    /** @type {{ dish_id: number, dates: string[] }[]} */
    const changes = [];

    for (const dish of dishItems) {
        const dishKey = String(dish.id);
        const original = sortDates(filterFutureDates(originalSchedule[dishKey] ?? [], editableFrom));
        const current = sortDates(filterFutureDates(editedSchedule[dishKey] ?? [], editableFrom));

        if (!areDateListsEqual(original, current)) {
            changes.push({
                dish_id: dish.id,
                dates: current,
            });
        }
    }

    return changes;
}
