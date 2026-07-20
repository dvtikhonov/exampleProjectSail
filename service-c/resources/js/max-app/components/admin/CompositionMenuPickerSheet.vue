<script setup>
/**
 * Sheet выбора блюда из меню ресторана для добавления в состав заказа.
 */
import { toRef } from 'vue';
import MenuCategoryTabs from '../menu/MenuCategoryTabs.vue';
import MenuComboBuilderSheet from '../menu/MenuComboBuilderSheet.vue';
import MenuDishGrid from '../menu/MenuDishGrid.vue';
import { useMenuCategoryFilter } from '../../composables/useMenuCategoryFilter';

const props = defineProps({
    open: {
        type: Boolean,
        default: false,
    },
    menu: {
        type: Object,
        default: null,
    },
    loading: {
        type: Boolean,
        default: false,
    },
    error: {
        type: String,
        default: '',
    },
    comboBuilderOpen: {
        type: Boolean,
        default: false,
    },
    comboFirstDish: {
        type: Object,
        default: null,
    },
    comboSecondDish: {
        type: Object,
        default: null,
    },
    comboQuantity: {
        type: Number,
        default: 1,
    },
    comboTotal: {
        type: String,
        default: '0.00',
    },
    canAddCombo: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits([
    'close',
    'add-dish',
    'start-combo',
    'reset-second-combo',
    'change-combo-quantity',
    'add-combo',
    'select-second-combo-dish',
    'close-combo-builder',
]);

const {
    activeCategoryId,
    searchQuery,
    categoryTabs,
    filteredDishes,
} = useMenuCategoryFilter(toRef(props, 'menu'), {
    comboBuilderOpen: toRef(props, 'comboBuilderOpen'),
    comboFirstDish: toRef(props, 'comboFirstDish'),
});
</script>

<template>
    <Teleport to="body">
        <div
            v-if="open"
            class="fixed inset-0 z-50 flex items-end justify-center bg-black/40"
            @click.self="$emit('close')"
        >
            <div
                class="flex max-h-[85vh] w-full max-w-lg flex-col rounded-t-2xl bg-gray-50 shadow-xl"
                role="dialog"
                aria-modal="true"
                aria-labelledby="composition-menu-picker-title"
                @click.stop
            >
                <div class="shrink-0 border-b border-gray-200 bg-white px-4 py-3">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <h2 id="composition-menu-picker-title" class="text-base font-semibold text-gray-900">
                                Добавить в заказ
                            </h2>
                            <p class="mt-0.5 text-sm text-max-muted">
                                Выберите блюдо или соберите комбо
                            </p>
                        </div>
                        <button
                            type="button"
                            class="shrink-0 text-sm font-medium text-max-muted transition hover:text-gray-700"
                            @click="$emit('close')"
                        >
                            Закрыть
                        </button>
                    </div>
                </div>

                <MenuComboBuilderSheet
                    :open="comboBuilderOpen"
                    :combo-first-dish="comboFirstDish"
                    :combo-second-dish="comboSecondDish"
                    :combo-quantity="comboQuantity"
                    :combo-total="comboTotal"
                    :can-add-combo="canAddCombo"
                    @close="$emit('close-combo-builder')"
                    @reset-second="$emit('reset-second-combo')"
                    @change-quantity="(delta) => $emit('change-combo-quantity', delta)"
                    @add-combo="$emit('add-combo')"
                />

                <div v-if="loading" class="flex flex-1 items-center justify-center py-16">
                    <div class="h-8 w-8 animate-spin rounded-full border-4 border-max-primary border-t-transparent" />
                </div>

                <template v-else-if="menu">
                    <div class="shrink-0 border-b border-gray-200 bg-white py-2">
                        <MenuCategoryTabs
                            :category-tabs="categoryTabs"
                            :active-category-id="activeCategoryId"
                            :search-query="searchQuery"
                            @update:active-category-id="activeCategoryId = $event"
                            @update:search-query="searchQuery = $event"
                        />
                    </div>

                    <div class="min-h-0 flex-1 overflow-y-auto py-3">
                        <MenuDishGrid
                            v-if="filteredDishes.length > 0"
                            :dishes="filteredDishes"
                            :combo-builder-open="comboBuilderOpen"
                            :combo-first-dish="comboFirstDish"
                            :combo-second-dish="comboSecondDish"
                            @add-to-cart="(dish) => $emit('add-dish', dish)"
                            @start-combo="(dish) => $emit('start-combo', dish)"
                            @select-second-combo-dish="(dish) => $emit('select-second-combo-dish', dish)"
                        />
                        <p
                            v-else
                            class="px-4 py-8 text-center text-sm text-max-muted"
                        >
                            Нет доступных блюд
                        </p>
                    </div>
                </template>

                <p
                    v-if="error"
                    class="shrink-0 border-t border-red-100 bg-red-50 px-4 py-3 text-sm text-red-700"
                >
                    {{ error }}
                </p>
            </div>
        </div>
    </Teleport>
</template>
