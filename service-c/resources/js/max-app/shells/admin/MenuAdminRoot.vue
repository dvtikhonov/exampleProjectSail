<script setup>
/**
 * Раздел управления меню: блюда, категории, график доступности.
 */
import { onMounted, onUnmounted, ref, watch } from 'vue';
import ConfirmDeleteModal from '../../components/ConfirmDeleteModal.vue';
import { useAdminChrome } from '../../composables/useAdminChrome';
import { useAuth } from '../../composables/useAuth';
import { useDishAdmin } from '../../composables/useDishAdmin';
import { useDishAdminFilters } from '../../composables/useDishAdminFilters';
import { useDishAvailabilitySchedule } from '../../composables/useDishAvailabilitySchedule';
import { useMaxBackButton } from '../../composables/useMaxBackButton';
import { useMenuCategoryAdmin } from '../../composables/useMenuCategoryAdmin';
import { ADMIN_DISH_VIEWS, ADMIN_SECTIONS } from '../../constants/views';
import AdminDishAvailabilityPage from '../../pages/admin/AdminDishAvailabilityPage.vue';
import AdminDishFormPage from '../../pages/admin/AdminDishFormPage.vue';
import AdminDishListPage from '../../pages/admin/AdminDishListPage.vue';
import AdminMenuCategoryFormPage from '../../pages/admin/AdminMenuCategoryFormPage.vue';
import AdminMenuCategoryListPage from '../../pages/admin/AdminMenuCategoryListPage.vue';

const {
    adminSection,
    hasAdminRoles,
    hasMenuManagerRole,
} = useAuth();

const { sectionNavVisible } = useAdminChrome();

const dishFilters = useDishAdminFilters();
const dishAdmin = useDishAdmin({ filters: dishFilters });
const categoryAdmin = useMenuCategoryAdmin({
    filters: dishFilters,
    onCategoriesChanged: () => dishAdmin.loadCategories(),
});

const {
    dishAdminView,
    dishes,
    dishesLoading,
    dishesRefreshing,
    dishesError,
    filterRestaurantId,
    filterCategoryId,
    filterNameSearch,
    filterAvailability,
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
    pendingDeleteDish,
    importLoading,
    importError,
    importSuccessMessage,
    initDishAdminSession,
    loadDishes,
    handleFilterRestaurantChange,
    handleFilterCategoryChange,
    handleFilterNameSearchChange,
    handleFilterAvailabilityChange,
    openCreateForm,
    openEditForm,
    openDishListView,
    openDishScheduleView,
    closeDishForm,
    handleFormRestaurantChange,
    submitDishForm,
    requestDeleteDish,
    cancelDeleteDish,
    confirmDeleteDish,
    handleImportClick,
    handleImportFile,
} = dishAdmin;

const {
    filteredCategories,
    categoriesLoading,
    categoriesRefreshing,
    categoriesError,
    restaurantOptions: categoryRestaurantOptions,
    editingCategory,
    categoryFormLoading,
    categoryFormError,
    categoryFormFieldErrors,
    categoryDeleteLoadingId,
    categoryDeleteError,
    pendingDeleteCategory,
    loadRestaurantOptions,
    loadMenuCategories,
    openCategoryListView,
    openCreateCategoryForm,
    openEditCategoryForm,
    closeCategoryForm,
    submitCategoryForm,
    requestDeleteCategory,
    cancelDeleteCategory,
    confirmDeleteCategory,
    handleCategoryFilterRestaurantChange,
} = categoryAdmin;

const dishSchedule = useDishAvailabilitySchedule({ categories: dishAdmin.categories, filters: dishFilters });
const {
    categoryOptions: scheduleCategoryOptions,
    filteredDishes: scheduleFilteredDishes,
    dates: scheduleDates,
    localSchedule: scheduleLocalSchedule,
    editableFrom: scheduleEditableFrom,
    loading: scheduleLoading,
    saving: scheduleSaving,
    error: scheduleError,
    saveError: scheduleSaveError,
    filtersReady: scheduleFiltersReady,
    hasUnsavedChanges: scheduleHasUnsavedChanges,
    loadSchedule,
    handleFilterRestaurantChange: handleScheduleFilterRestaurantChange,
    handleFilterCategoryChange: handleScheduleFilterCategoryChange,
    handleFilterNameSearchChange: handleScheduleFilterNameSearchChange,
    isDateEditable: isScheduleDateEditable,
    isAvailable: isScheduleDateAvailable,
    toggleAvailability: toggleScheduleAvailability,
    saveSchedule,
} = dishSchedule;

const dishListPageRef = ref(null);

const back = useMaxBackButton({
    hasAdminRoles,
    adminSection,
    hasMenuManagerRole,
    dishAdmin,
    categoryAdmin,
});

/**
 * @param {object} fields
 * @param {File|null} photoFile
 */
function handleDishFormSubmit(fields, photoFile) {
    submitDishForm(fields, photoFile);
}

function onDishImportClick() {
    if (handleImportClick()) {
        dishListPageRef.value?.openFilePicker();
    }
}

/**
 * @param {File} file
 */
function onDishImportFile(file) {
    handleImportFile(file);
}

/**
 * @param {object} fields
 */
function handleCategoryFormSubmit(fields) {
    submitCategoryForm(dishAdminView, fields);
}

function handleCloseCategoryForm() {
    closeCategoryForm(dishAdminView);
}

function handleOpenCreateCategoryForm() {
    openCreateCategoryForm(dishAdminView);
}

/**
 * @param {object} category
 */
function handleOpenEditCategoryForm(category) {
    openEditCategoryForm(dishAdminView, category);
}

/**
 * @param {string} value
 */
function handleCategoryFilterRestaurant(value) {
    handleCategoryFilterRestaurantChange(value, dishAdminView);
}

function handleOpenDishListView() {
    if (dishAdminView.value === ADMIN_DISH_VIEWS.list) {
        return;
    }

    openDishListView();
    loadDishes();
    back.setupBackButton();
}

function handleOpenCategoryListView() {
    if (dishAdminView.value === ADMIN_DISH_VIEWS.categoryList) {
        return;
    }

    openCategoryListView(dishAdminView);
    loadRestaurantOptions();
    loadMenuCategories();
    back.setupBackButton();
}

function handleOpenDishScheduleView() {
    if (dishAdminView.value === ADMIN_DISH_VIEWS.schedule) {
        return;
    }

    openDishScheduleView();
    loadSchedule();
    back.setupBackButton();
}

/**
 * @param {number} dishId
 * @param {string} date
 */
function handleScheduleToggle(dishId, date) {
    toggleScheduleAvailability(dishId, date);
}

watch(
    dishAdminView,
    (view) => {
        sectionNavVisible.value = view !== ADMIN_DISH_VIEWS.form
            && view !== ADMIN_DISH_VIEWS.categoryForm;
    },
    { immediate: true },
);

onMounted(() => {
    if (adminSection.value === ADMIN_SECTIONS.menu) {
        initDishAdminSession();
    }

    back.setupBackButton();
});

onUnmounted(() => {
    sectionNavVisible.value = true;
});
</script>

<template>
    <div class="flex min-h-0 flex-1 flex-col overflow-hidden">
        <nav
            v-if="dishAdminView !== ADMIN_DISH_VIEWS.form && dishAdminView !== ADMIN_DISH_VIEWS.categoryForm"
            class="z-10 shrink-0 border-b border-gray-100 bg-white"
            aria-label="Режим управления меню"
        >
            <div class="flex">
                <button
                    type="button"
                    class="flex-1 border-b-2 px-4 py-2.5 text-sm font-medium transition"
                    :class="
                        dishAdminView === ADMIN_DISH_VIEWS.list
                            ? 'border-max-primary text-max-primary'
                            : 'border-transparent text-max-muted hover:text-gray-700'
                    "
                    @click="handleOpenDishListView"
                >
                    Блюда
                </button>
                <button
                    type="button"
                    class="flex-1 border-b-2 px-4 py-2.5 text-sm font-medium transition"
                    :class="
                        dishAdminView === ADMIN_DISH_VIEWS.categoryList
                            || dishAdminView === ADMIN_DISH_VIEWS.categoryForm
                            ? 'border-max-primary text-max-primary'
                            : 'border-transparent text-max-muted hover:text-gray-700'
                    "
                    @click="handleOpenCategoryListView"
                >
                    Категории
                </button>
                <button
                    type="button"
                    class="flex-1 border-b-2 px-4 py-2.5 text-sm font-medium transition"
                    :class="
                        dishAdminView === ADMIN_DISH_VIEWS.schedule
                            ? 'border-max-primary text-max-primary'
                            : 'border-transparent text-max-muted hover:text-gray-700'
                    "
                    @click="handleOpenDishScheduleView"
                >
                    График
                </button>
            </div>
        </nav>

        <AdminMenuCategoryFormPage
            v-if="dishAdminView === ADMIN_DISH_VIEWS.categoryForm"
            :category="editingCategory"
            :restaurant-options="categoryRestaurantOptions"
            :loading="categoryFormLoading && !editingCategory"
            :submit-loading="categoryFormLoading"
            :error="categoryFormError"
            :server-field-errors="categoryFormFieldErrors"
            @back="handleCloseCategoryForm"
            @submit="handleCategoryFormSubmit"
        />

        <KeepAlive>
            <AdminMenuCategoryListPage
                v-if="dishAdminView === ADMIN_DISH_VIEWS.categoryList"
                class="min-h-0 flex-1"
                :categories="filteredCategories"
                :loading="categoriesLoading"
                :error="categoriesError"
                :refreshing="categoriesRefreshing"
                :delete-error="categoryDeleteError"
                :delete-loading-id="categoryDeleteLoadingId"
                :restaurant-options="categoryRestaurantOptions"
                :filter-restaurant-id="filterRestaurantId"
                @add="handleOpenCreateCategoryForm"
                @edit="handleOpenEditCategoryForm"
                @delete="requestDeleteCategory"
                @refresh="loadMenuCategories({ refreshing: true })"
                @filter-restaurant="handleCategoryFilterRestaurant"
            />
        </KeepAlive>

        <AdminDishFormPage
            v-if="dishAdminView === ADMIN_DISH_VIEWS.form"
            :dish="editingDish"
            :category-options="categoryFormOptions"
            :restaurant-options="restaurantOptions"
            :restaurant-id="formRestaurantId"
            :loading="formLoading && !editingDish"
            :submit-loading="formLoading"
            :error="formError"
            :server-field-errors="formFieldErrors"
            @back="closeDishForm"
            @update:restaurant-id="handleFormRestaurantChange"
            @submit="handleDishFormSubmit"
        />

        <KeepAlive>
            <AdminDishListPage
                v-if="dishAdminView === ADMIN_DISH_VIEWS.list"
                ref="dishListPageRef"
                class="min-h-0 flex-1"
                :dishes="dishes"
                :loading="dishesLoading"
                :error="dishesError"
                :refreshing="dishesRefreshing"
                :delete-error="deleteError"
                :delete-loading-id="deleteLoadingId"
                :restaurant-options="restaurantOptions"
                :category-options="categoryFilterOptions"
                :filter-restaurant-id="filterRestaurantId"
                :filter-category-id="filterCategoryId"
                :filter-name-search="filterNameSearch"
                :filter-availability="filterAvailability"
                :import-loading="importLoading"
                :import-error="importError"
                :import-success-message="importSuccessMessage"
                @add="openCreateForm"
                @edit="openEditForm"
                @delete="requestDeleteDish"
                @refresh="loadDishes({ refreshing: true })"
                @filter-restaurant="handleFilterRestaurantChange"
                @filter-category="handleFilterCategoryChange"
                @filter-name-search="handleFilterNameSearchChange"
                @filter-availability="handleFilterAvailabilityChange"
                @import-click="onDishImportClick"
                @import="onDishImportFile"
            />
        </KeepAlive>

        <AdminDishAvailabilityPage
            v-if="dishAdminView === ADMIN_DISH_VIEWS.schedule"
            class="min-h-0 flex-1"
            :dishes="scheduleFilteredDishes"
            :dates="scheduleDates"
            :editable-from="scheduleEditableFrom"
            :loading="scheduleLoading"
            :saving="scheduleSaving"
            :error="scheduleError"
            :save-error="scheduleSaveError"
            :filters-ready="scheduleFiltersReady"
            :has-unsaved-changes="scheduleHasUnsavedChanges"
            :restaurant-options="restaurantOptions"
            :category-options="scheduleCategoryOptions"
            :filter-restaurant-id="filterRestaurantId"
            :filter-category-id="filterCategoryId"
            :filter-name-search="filterNameSearch"
            :is-date-editable="isScheduleDateEditable"
            :is-available="isScheduleDateAvailable"
            :schedule="scheduleLocalSchedule"
            @filter-restaurant="handleScheduleFilterRestaurantChange"
            @filter-category="handleScheduleFilterCategoryChange"
            @filter-name-search="handleScheduleFilterNameSearchChange"
            @toggle="handleScheduleToggle"
            @save="saveSchedule"
            @refresh="loadSchedule"
        />

        <ConfirmDeleteModal
            :open="pendingDeleteDish !== null"
            title="Удалить блюдо?"
            :message="pendingDeleteDish ? `Удалить блюдо «${pendingDeleteDish.name}»?` : ''"
            :loading="pendingDeleteDish !== null && deleteLoadingId === pendingDeleteDish.id"
            :error="deleteError"
            @close="cancelDeleteDish"
            @confirm="confirmDeleteDish"
        />

        <ConfirmDeleteModal
            :open="pendingDeleteCategory !== null"
            title="Удалить категорию?"
            :message="pendingDeleteCategory ? `Удалить категорию «${pendingDeleteCategory.name}»?` : ''"
            :loading="pendingDeleteCategory !== null && categoryDeleteLoadingId === pendingDeleteCategory.id"
            :error="categoryDeleteError"
            @close="cancelDeleteCategory"
            @confirm="confirmDeleteCategory"
        />
    </div>
</template>
