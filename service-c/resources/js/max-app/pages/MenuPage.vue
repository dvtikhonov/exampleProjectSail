<script setup>
/**
 * Меню ресторана: категории и блюда в сетке 2 колонки.
 * В категориях с is_combo_available — сборка комбо через bottom sheet.
 * При непустой корзине показывает фиксированную панель внизу с итогом.
 */
import { computed, ref, toRef } from 'vue';
import MenuCategoryTabs from '../components/menu/MenuCategoryTabs.vue';
import MenuComboBuilderSheet from '../components/menu/MenuComboBuilderSheet.vue';
import MenuDishGrid from '../components/menu/MenuDishGrid.vue';
import MenuHeader from '../components/menu/MenuHeader.vue';
import { useMenuCategoryFilter } from '../composables/useMenuCategoryFilter';

const props = defineProps({
    menu: {
        type: Object,
        default: null,
    },
    deliveryAddress: {
        type: String,
        default: '',
    },
    loading: {
        type: Boolean,
        default: false,
    },
    error: {
        type: String,
        default: '',
    },
    addingDishId: {
        type: Number,
        default: null,
    },
    addingComboRef: {
        type: String,
        default: null,
    },
    cartItemCount: {
        type: Number,
        default: 0,
    },
    cartTotal: {
        type: String,
        default: '',
    },
    ordersUnreadCount: {
        type: Number,
        default: 0,
    },
});

const emit = defineEmits(['add-to-cart', 'add-combo-to-cart', 'open-cart', 'open-orders']);

const hasCart = computed(() => props.cartItemCount > 0);

const comboBuilderOpen = ref(false);
const comboFirstDish = ref(null);
const comboSecondDish = ref(null);
const comboQuantity = ref(1);

const {
    activeCategoryId,
    searchQuery,
    categoryTabs,
    filteredDishes,
} = useMenuCategoryFilter(toRef(props, 'menu'), {
    comboBuilderOpen,
    comboFirstDish,
});

const comboTotal = computed(() => {
    if (!comboFirstDish.value || !comboSecondDish.value) {
        return '0.00';
    }

    const total = (
        Number.parseFloat(comboFirstDish.value.price)
        + Number.parseFloat(comboSecondDish.value.price)
    ) * comboQuantity.value;

    return total.toFixed(2);
});

const canAddCombo = computed(
    () => comboFirstDish.value !== null
        && comboSecondDish.value !== null
        && props.addingComboRef === null,
);

function dishWithCategory(dish) {
    return {
        ...dish,
        category_id: dish.category_id,
        category_name: dish.category_name,
    };
}

function startComboBuilder(dish) {
    if (props.addingComboRef !== null) {
        return;
    }

    comboBuilderOpen.value = true;
    comboFirstDish.value = dishWithCategory(dish);
    comboSecondDish.value = null;
    comboQuantity.value = 1;
}

function closeComboBuilder() {
    comboBuilderOpen.value = false;
    comboFirstDish.value = null;
    comboSecondDish.value = null;
    comboQuantity.value = 1;
}

function resetSecondComboDish() {
    comboSecondDish.value = null;
    comboQuantity.value = 1;
}

function selectSecondComboDish(dish) {
    const selectedDish = dishWithCategory(dish);

    if (comboSecondDish.value?.id === selectedDish.id) {
        comboSecondDish.value = null;

        return;
    }

    comboSecondDish.value = selectedDish;
}

function changeComboQuantity(delta) {
    comboQuantity.value = Math.min(99, Math.max(1, comboQuantity.value + delta));
}

function addComboToCart() {
    if (!canAddCombo.value) {
        return;
    }

    emit('add-combo-to-cart', {
        firstDish: comboFirstDish.value,
        secondDish: comboSecondDish.value,
        quantity: comboQuantity.value,
        comboRef: generateComboRef(),
    });

    closeComboBuilder();
}

function generateComboRef() {
    if (window.crypto?.randomUUID) {
        return window.crypto.randomUUID();
    }

    return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (char) => {
        const random = Math.trunc(Math.random() * 16);
        const value = char === 'x' ? random : (random & 0x3) | 0x8;

        return value.toString(16);
    });
}
</script>

<template>
    <div class="flex min-h-dvh flex-col bg-white pb-24">
        <MenuHeader
            :delivery-address="deliveryAddress"
            :restaurant-name="menu?.restaurant_name ?? ''"
            :orders-unread-count="ordersUnreadCount"
            @open-cart="emit('open-cart')"
            @open-orders="emit('open-orders')"
        />

        <main class="flex-1 py-4">
            <div v-if="loading" class="flex items-center justify-center py-16">
                <div class="h-8 w-8 animate-spin rounded-full border-4 border-max-primary border-t-transparent" />
            </div>

            <div
                v-else-if="error"
                class="mx-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"
            >
                {{ error }}
            </div>

            <div v-else-if="menu" class="space-y-4">
                <MenuCategoryTabs
                    :category-tabs="categoryTabs"
                    :active-category-id="activeCategoryId"
                    :search-query="searchQuery"
                    @update:active-category-id="activeCategoryId = $event"
                    @update:search-query="searchQuery = $event"
                />

                <p
                    v-if="filteredDishes.length === 0"
                    class="py-16 text-center text-sm text-max-muted"
                >
                    Сейчас нет доступных блюд
                </p>

                <MenuDishGrid
                    v-else
                    :dishes="filteredDishes"
                    :adding-dish-id="addingDishId"
                    :adding-combo-ref="addingComboRef"
                    :combo-builder-open="comboBuilderOpen"
                    :combo-first-dish="comboFirstDish"
                    :combo-second-dish="comboSecondDish"
                    @add-to-cart="emit('add-to-cart', $event)"
                    @start-combo="startComboBuilder"
                    @select-second-combo-dish="selectSecondComboDish"
                />
            </div>
        </main>

        <MenuComboBuilderSheet
            :open="comboBuilderOpen"
            :combo-first-dish="comboFirstDish"
            :combo-second-dish="comboSecondDish"
            :combo-quantity="comboQuantity"
            :combo-total="comboTotal"
            :can-add-combo="canAddCombo"
            :adding-combo-ref="addingComboRef"
            @close="closeComboBuilder"
            @reset-second="resetSecondComboDish"
            @change-quantity="changeComboQuantity"
            @add-combo="addComboToCart"
        />

        <div
            v-if="hasCart"
            class="fixed inset-x-0 bottom-0 z-20 border-t border-gray-200 bg-white px-4 py-3 safe-area-bottom"
        >
            <button
                type="button"
                class="flex w-full items-center justify-between rounded-2xl bg-max-primary px-4 py-3.5 text-white transition hover:bg-max-primary-hover"
                @click="emit('open-cart')"
            >
                <span class="font-medium">Корзина · {{ cartItemCount }} {{ cartItemCount === 1 ? 'позиция' : 'позиций' }}</span>
                <span class="font-semibold">{{ cartTotal }} ₽</span>
            </button>
        </div>
    </div>
</template>
