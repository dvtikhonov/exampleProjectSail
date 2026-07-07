/**
 * Админ-поток CRUD категорий меню.
 */
import { computed, ref } from 'vue';
import {
    createMenuCategory,
    deleteMenuCategory,
    extractErrorMessage,
    extractValidationErrors,
    fetchAdminMenuCategories,
    fetchAdminMenuCategory,
    fetchRestaurants,
    updateMenuCategory,
} from '../api/foodClient';
import { ADMIN_DISH_VIEWS } from '../constants/views';

/**
 * @param {{
 *   filters: ReturnType<typeof import('./useDishAdminFilters').useDishAdminFilters>,
 *   onCategoriesChanged?: () => void | Promise<void>,
 * }} options
 */
export function useMenuCategoryAdmin({ filters, onCategoriesChanged = async () => {} }) {
    const menuCategories = ref([]);
    const categoriesLoading = ref(false);
    const categoriesRefreshing = ref(false);
    const categoriesError = ref('');

    const restaurantOptions = ref([]);

    const editingCategory = ref(null);
    const categoryFormLoading = ref(false);
    const categoryFormError = ref('');
    const categoryFormFieldErrors = ref({});

    const categoryDeleteLoadingId = ref(null);
    const categoryDeleteError = ref('');

    const { filterRestaurantId } = filters;

    const filteredCategories = computed(() => {
        const restaurantId = filterRestaurantId.value ? Number(filterRestaurantId.value) : null;

        if (restaurantId === null) {
            return menuCategories.value;
        }

        return menuCategories.value.filter((category) => category.restaurant_id === restaurantId);
    });

    async function loadRestaurantOptions() {
        try {
            restaurantOptions.value = await fetchRestaurants();
        } catch (error) {
            categoriesError.value = extractErrorMessage(error);
        }
    }

    async function loadMenuCategories({ refreshing = false } = {}) {
        if (refreshing) {
            categoriesRefreshing.value = true;
        } else {
            categoriesLoading.value = true;
        }

        categoriesError.value = '';

        try {
            const restaurantId = filterRestaurantId.value ? Number(filterRestaurantId.value) : null;
            menuCategories.value = await fetchAdminMenuCategories(restaurantId);
        } catch (error) {
            categoriesError.value = extractErrorMessage(error);
        } finally {
            categoriesLoading.value = false;
            categoriesRefreshing.value = false;
        }
    }

    function openCategoryListView(dishAdminView) {
        dishAdminView.value = ADMIN_DISH_VIEWS.categoryList;
    }

    function openCreateCategoryForm(dishAdminView) {
        editingCategory.value = null;
        categoryFormError.value = '';
        categoryFormFieldErrors.value = {};
        dishAdminView.value = ADMIN_DISH_VIEWS.categoryForm;
    }

    /**
     * @param {object} category
     */
    async function openEditCategoryForm(dishAdminView, category) {
        categoryFormError.value = '';
        categoryFormFieldErrors.value = {};
        dishAdminView.value = ADMIN_DISH_VIEWS.categoryForm;
        categoryFormLoading.value = true;
        editingCategory.value = { ...category };

        try {
            editingCategory.value = await fetchAdminMenuCategory(category.id);
        } catch (error) {
            categoryFormError.value = extractErrorMessage(error);
            dishAdminView.value = ADMIN_DISH_VIEWS.categoryList;
            editingCategory.value = null;
        } finally {
            categoryFormLoading.value = false;
        }
    }

    /**
     * @param {import('vue').Ref<string>} dishAdminView
     * @param {{ reload?: boolean }} [options]
     */
    async function closeCategoryForm(dishAdminView, { reload = false } = {}) {
        dishAdminView.value = ADMIN_DISH_VIEWS.categoryList;
        editingCategory.value = null;
        categoryFormError.value = '';
        categoryFormFieldErrors.value = {};

        if (reload) {
            await loadMenuCategories();
            await onCategoriesChanged();
        }
    }

    /**
     * @param {import('vue').Ref<string>} dishAdminView
     * @param {object} fields
     */
    async function submitCategoryForm(dishAdminView, fields) {
        categoryFormLoading.value = true;
        categoryFormError.value = '';
        categoryFormFieldErrors.value = {};

        try {
            if (editingCategory.value?.id) {
                await updateMenuCategory(editingCategory.value.id, fields);
            } else {
                await createMenuCategory(fields);
            }

            await closeCategoryForm(dishAdminView, { reload: true });
        } catch (error) {
            const validationErrors = extractValidationErrors(error);

            if (Object.keys(validationErrors).length > 0) {
                categoryFormFieldErrors.value = validationErrors;
            } else {
                categoryFormError.value = extractErrorMessage(error);
            }
        } finally {
            categoryFormLoading.value = false;
        }
    }

    /**
     * @param {import('vue').Ref<string>} dishAdminView
     * @param {object} category
     */
    async function handleDeleteCategory(dishAdminView, category) {
        if (!window.confirm(`Удалить категорию «${category.name}»?`)) {
            return;
        }

        categoryDeleteLoadingId.value = category.id;
        categoryDeleteError.value = '';

        try {
            await deleteMenuCategory(category.id);
            await loadMenuCategories();
            await onCategoriesChanged();
        } catch (error) {
            categoryDeleteError.value = extractErrorMessage(error);
        } finally {
            categoryDeleteLoadingId.value = null;
        }
    }

    function handleCategoryFilterRestaurantChange(value, dishAdminView) {
        filterRestaurantId.value = value;

        if (dishAdminView.value === ADMIN_DISH_VIEWS.categoryList) {
            loadMenuCategories();
        }
    }

    return {
        menuCategories,
        filteredCategories,
        categoriesLoading,
        categoriesRefreshing,
        categoriesError,
        restaurantOptions,
        editingCategory,
        categoryFormLoading,
        categoryFormError,
        categoryFormFieldErrors,
        categoryDeleteLoadingId,
        categoryDeleteError,
        loadRestaurantOptions,
        loadMenuCategories,
        openCategoryListView,
        openCreateCategoryForm,
        openEditCategoryForm,
        closeCategoryForm,
        submitCategoryForm,
        handleDeleteCategory,
        handleCategoryFilterRestaurantChange,
    };
}
