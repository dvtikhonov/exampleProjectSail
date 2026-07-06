/**
 * Админ-поток CRUD блюд: список, форма create/edit, фильтры, удаление.
 */
import { computed, onScopeDispose, ref } from 'vue';
import {
    createDish,
    deleteDish,
    extractErrorMessage,
    extractValidationErrors,
    fetchAdminDish,
    fetchAdminDishes,
    fetchAdminMenuCategories,
    importDishesSpreadsheet,
    updateDish,
} from '../api/foodClient';
import { ADMIN_DISH_VIEWS } from '../constants/views';

/** Задержка debounce поиска по названию (мс) */
const NAME_SEARCH_DEBOUNCE_MS = 300;

/**
 * @param {{ filters: ReturnType<typeof import('./useDishAdminFilters').useDishAdminFilters> }} options
 * @returns {object} Состояние и обработчики управления блюдами
 */
export function useDishAdmin({ filters }) {
    const dishAdminView = ref(ADMIN_DISH_VIEWS.list);

    const dishes = ref([]);
    const categories = ref([]);
    const dishesLoading = ref(false);
    const dishesRefreshing = ref(false);
    const dishesError = ref('');

    const { filterRestaurantId, filterCategoryId, filterNameSearch } = filters;
    const formRestaurantId = ref('');

    /** Таймер debounce для поиска по названию */
    let nameSearchDebounceTimer = null;

    const editingDish = ref(null);
    const formLoading = ref(false);
    const formError = ref('');
    const formFieldErrors = ref({});

    const deleteLoadingId = ref(null);
    const deleteError = ref('');

    const importLoading = ref(false);
    const importError = ref('');
    const importSuccessMessage = ref('');

    const restaurantOptions = computed(() => {
        const map = new Map();

        for (const category of categories.value) {
            if (!map.has(category.restaurant_id)) {
                map.set(category.restaurant_id, category.restaurant_name);
            }
        }

        return [...map.entries()]
            .map(([id, name]) => ({ id, name }))
            .sort((a, b) => a.name.localeCompare(b.name, 'ru'));
    });

    const categoryFilterOptions = computed(() => {
        const restaurantId = filterRestaurantId.value ? Number(filterRestaurantId.value) : null;
        const items = restaurantId !== null
            ? categories.value.filter((category) => category.restaurant_id === restaurantId)
            : categories.value;

        return items
            .map((category) => ({
                id: category.id,
                name: category.name,
                restaurantName: category.restaurant_name,
            }))
            .sort((a, b) => a.name.localeCompare(b.name, 'ru'));
    });

    const categoryFormOptions = computed(() => {
        const restaurantId = formRestaurantId.value ? Number(formRestaurantId.value) : null;

        if (restaurantId === null) {
            return [];
        }

        return categories.value
            .filter((category) => category.restaurant_id === restaurantId)
            .map((category) => ({
                id: category.id,
                label: category.name,
            }))
            .sort((a, b) => a.label.localeCompare(b.label, 'ru'));
    });

    function clearNameSearchDebounceTimer() {
        if (nameSearchDebounceTimer !== null) {
            clearTimeout(nameSearchDebounceTimer);
            nameSearchDebounceTimer = null;
        }
    }

    onScopeDispose(() => {
        clearNameSearchDebounceTimer();
    });

    /** Сброс состояния и загрузка данных при входе в раздел «Меню» */
    function initDishAdminSession() {
        dishAdminView.value = ADMIN_DISH_VIEWS.list;
        editingDish.value = null;
        formError.value = '';
        formFieldErrors.value = {};
        deleteError.value = '';
        loadCategories();
        loadDishes();
    }

    async function loadCategories() {
        try {
            categories.value = await fetchAdminMenuCategories();
        } catch (error) {
            dishesError.value = extractErrorMessage(error);
        }
    }

    /**
     * @param {{ refreshing?: boolean }} [options]
     */
    async function loadDishes({ refreshing = false } = {}) {
        if (refreshing) {
            dishesRefreshing.value = true;
        } else {
            dishesLoading.value = true;
        }

        dishesError.value = '';

        try {
            const restaurantId = filterRestaurantId.value ? Number(filterRestaurantId.value) : null;
            const categoryId = filterCategoryId.value ? Number(filterCategoryId.value) : null;
            const nameSearch = filterNameSearch.value.trim();

            dishes.value = await fetchAdminDishes({
                restaurantId,
                categoryId,
                name: nameSearch || null,
            });
        } catch (error) {
            dishesError.value = extractErrorMessage(error);
        } finally {
            dishesLoading.value = false;
            dishesRefreshing.value = false;
        }
    }

    function handleFilterRestaurantChange(value) {
        filterRestaurantId.value = value;
        filterCategoryId.value = '';
        loadDishes();
    }

    function handleFilterCategoryChange(value) {
        filterCategoryId.value = value;
        loadDishes();
    }

    function handleFilterNameSearchChange(value) {
        filterNameSearch.value = value;
        clearNameSearchDebounceTimer();
        nameSearchDebounceTimer = setTimeout(() => {
            nameSearchDebounceTimer = null;
            loadDishes();
        }, NAME_SEARCH_DEBOUNCE_MS);
    }

    function openCreateForm() {
        editingDish.value = null;
        formRestaurantId.value = filterRestaurantId.value;
        formError.value = '';
        formFieldErrors.value = {};
        dishAdminView.value = ADMIN_DISH_VIEWS.form;
    }

    function openDishListView() {
        dishAdminView.value = ADMIN_DISH_VIEWS.list;
    }

    function openDishScheduleView() {
        dishAdminView.value = ADMIN_DISH_VIEWS.schedule;
    }

    /**
     * @param {object} dish
     */
    async function openEditForm(dish) {
        formError.value = '';
        formFieldErrors.value = {};
        dishAdminView.value = ADMIN_DISH_VIEWS.form;
        formLoading.value = true;
        // Данные из списка сразу — форма показывает текущее фото до завершения fetch
        editingDish.value = { ...dish };
        formRestaurantId.value = String(dish.restaurant_id);

        try {
            const fetched = await fetchAdminDish(dish.id);
            editingDish.value = {
                ...fetched,
                image_url: fetched.image_url ?? dish.image_url ?? null,
            };
            formRestaurantId.value = String(editingDish.value.restaurant_id);
        } catch (error) {
            formError.value = extractErrorMessage(error);
            dishAdminView.value = ADMIN_DISH_VIEWS.list;
            editingDish.value = null;
        } finally {
            formLoading.value = false;
        }
    }

    /**
     * @param {{ reload?: boolean }} [options]
     */
    async function closeDishForm({ reload = false } = {}) {
        dishAdminView.value = ADMIN_DISH_VIEWS.list;
        editingDish.value = null;
        formRestaurantId.value = '';
        formError.value = '';
        formFieldErrors.value = {};

        if (reload) {
            await loadDishes();
        }
    }

    /**
     * @param {string} value
     */
    function handleFormRestaurantChange(value) {
        formRestaurantId.value = value;
    }

    /**
     * @param {object} fields
     * @param {File|null} photoFile
     */
    async function submitDishForm(fields, photoFile) {
        formLoading.value = true;
        formError.value = '';
        formFieldErrors.value = {};

        try {
            if (editingDish.value?.id) {
                await updateDish(editingDish.value.id, fields, photoFile);
            } else {
                if (!photoFile) {
                    throw new Error('Загрузите фотографию блюда.');
                }

                await createDish(fields, photoFile);
            }

            await closeDishForm({ reload: true });
        } catch (error) {
            const validationErrors = extractValidationErrors(error);

            if (Object.keys(validationErrors).length > 0) {
                formFieldErrors.value = validationErrors;
            } else {
                formError.value = extractErrorMessage(error);
            }
        } finally {
            formLoading.value = false;
        }
    }

    /**
     * @returns {boolean}
     */
    function validateImportFilters() {
        importSuccessMessage.value = '';

        if (filterRestaurantId.value === '' || filterCategoryId.value === '') {
            importError.value = 'Выберите ресторан и категорию перед загрузкой';

            return false;
        }

        const categoryId = Number(filterCategoryId.value);

        if (!Number.isFinite(categoryId)) {
            importError.value = 'Категория не найдена';

            return false;
        }

        importError.value = '';

        return true;
    }

    /**
     * @returns {boolean} Можно открыть выбор файла
     */
    function handleImportClick() {
        return validateImportFilters();
    }

    /**
     * @param {File} file
     */
    async function handleImportFile(file) {
        importSuccessMessage.value = '';
        importError.value = '';

        const extension = file.name.split('.').pop()?.toLowerCase();

        if (extension !== 'xls' && extension !== 'xlsx') {
            importError.value = 'Выберите файл в формате .xls или .xlsx';

            return;
        }

        const categoryId = Number(filterCategoryId.value);

        if (!Number.isFinite(categoryId)) {
            importError.value = 'Категория не найдена';

            return;
        }

        importLoading.value = true;

        try {
            const result = await importDishesSpreadsheet(file, categoryId);
            await loadDishes();
            importSuccessMessage.value = `Загружено ${result.imported_count} блюд`;
        } catch (error) {
            importError.value = formatImportError(error);
        } finally {
            importLoading.value = false;
        }
    }

    /**
     * @param {unknown} error
     * @returns {string}
     */
    function formatImportError(error) {
        if (error && typeof error === 'object' && 'response' in error) {
            const response = error.response;
            const responseErrors = response?.data?.errors;

            if (Array.isArray(responseErrors) && responseErrors.length > 0) {
                return responseErrors
                    .map((item) => {
                        if (item && typeof item === 'object' && 'row' in item && 'message' in item) {
                            return `Строка ${item.row}: ${item.message}`;
                        }

                        return String(item);
                    })
                    .join('\n');
            }
        }

        return extractErrorMessage(error);
    }

    /**
     * @param {object} dish
     */
    async function handleDeleteDish(dish) {
        if (!window.confirm(`Удалить блюдо «${dish.name}»?`)) {
            return;
        }

        deleteLoadingId.value = dish.id;
        deleteError.value = '';

        try {
            await deleteDish(dish.id);
            await loadDishes();
        } catch (error) {
            deleteError.value = extractErrorMessage(error);
        } finally {
            deleteLoadingId.value = null;
        }
    }

    return {
        dishAdminView,
        dishes,
        categories,
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
    };
}
