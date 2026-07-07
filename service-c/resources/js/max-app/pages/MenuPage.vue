<script setup>
/**
 * Меню ресторана: категории и блюда с кнопкой «+» в корзину.
 * В категориях с is_combo_available — кнопка «собрать комбо» открывает панель сборки.
 * При непустой корзине показывает фиксированную панель внизу с итогом.
 */
import { computed, ref } from 'vue';
import DishImage from '../components/DishImage.vue';
import MyOrdersButton from '../components/MyOrdersButton.vue';

const props = defineProps({
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

const visibleCategories = computed(() => {
    if (!props.menu?.categories) {
        return [];
    }

    let categories = props.menu.categories
        .map((category) => ({
            ...category,
            dishes: category.dishes.filter((dish) => dish.is_available),
        }))
        .filter((category) => category.dishes.length > 0);

    if (comboBuilderOpen.value && comboFirstDish.value) {
        categories = categories.filter(
            (category) => category.id !== comboFirstDish.value.category_id,
        );
    }

    return categories;
});

const comboBuilderOpen = ref(false);
const comboFirstDish = ref(null);
const comboSecondDish = ref(null);
const comboQuantity = ref(1);

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

function dishWithCategory(dish, category) {
    return {
        ...dish,
        category_id: category.id,
        category_name: category.name,
    };
}

function categoryAllowsCombo(category) {
    return category.is_combo_available !== false;
}

function canSelectAsSecondDish(dish, category) {
    if (!comboBuilderOpen.value || !comboFirstDish.value || props.addingComboRef !== null) {
        return false;
    }

    if (comboFirstDish.value.category_id === category.id) {
        return false;
    }

    return comboFirstDish.value.id !== dish.id;
}

function startComboBuilder(dish, category) {
    if (props.addingComboRef !== null) {
        return;
    }

    comboBuilderOpen.value = true;
    comboFirstDish.value = dishWithCategory(dish, category);
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

function selectSecondComboDish(dish, category) {
    if (!canSelectAsSecondDish(dish, category)) {
        return;
    }

    const selectedDish = dishWithCategory(dish, category);

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
    <div class="flex min-h-dvh flex-col pb-24">
        <header class="sticky top-0 z-10 border-b border-gray-200 bg-white px-4 py-3">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <h1 class="truncate text-lg font-semibold text-gray-900">
                        {{ menu?.restaurant_name ?? 'Меню' }}
                    </h1>
                    <p class="text-sm text-max-muted">Выберите блюда</p>
                </div>
                <div class="flex shrink-0 items-center gap-2">
                    <MyOrdersButton
                        label="Заказы"
                        :unread-count="ordersUnreadCount"
                        button-class="rounded-full px-3 py-1.5 text-sm font-medium text-max-primary transition hover:bg-max-primary/10"
                        @click="emit('open-orders')"
                    />
                </div>
            </div>
        </header>

        <main class="flex-1 px-4 py-4">
            <div v-if="loading" class="flex items-center justify-center py-16">
                <div class="h-8 w-8 animate-spin rounded-full border-4 border-max-primary border-t-transparent" />
            </div>

            <div
                v-else-if="error"
                class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"
            >
                {{ error }}
            </div>

            <div v-else-if="menu" class="space-y-6">
                <section
                    v-if="comboBuilderOpen"
                    class="rounded-2xl border border-max-primary/20 bg-white p-4 shadow-sm"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <h2 class="text-base font-semibold text-gray-900">Собрать комбо</h2>
                            <p class="mt-1 text-sm text-max-muted">
                                Выберите второе блюдо из другой категории
                            </p>
                        </div>
                        <div class="flex shrink-0 items-center gap-2">
                            <button
                                v-if="comboSecondDish"
                                type="button"
                                class="text-sm font-medium text-max-primary"
                                @click="resetSecondComboDish"
                            >
                                Сбросить
                            </button>
                            <button
                                type="button"
                                class="text-sm font-medium text-max-muted hover:text-gray-700"
                                @click="closeComboBuilder"
                            >
                                Закрыть
                            </button>
                        </div>
                    </div>

                    <div class="mt-4 grid grid-cols-2 gap-2 text-sm">
                        <div class="rounded-xl bg-gray-50 p-3">
                            <p class="text-xs font-medium uppercase tracking-wide text-max-muted">Блюдо 1</p>
                            <p class="mt-1 font-medium text-gray-900">
                                {{ comboFirstDish?.name ?? 'Не выбрано' }}
                            </p>
                            <p v-if="comboFirstDish" class="mt-0.5 text-xs text-max-muted">
                                {{ comboFirstDish.category_name }}
                            </p>
                        </div>
                        <div class="rounded-xl bg-gray-50 p-3">
                            <p class="text-xs font-medium uppercase tracking-wide text-max-muted">Блюдо 2</p>
                            <p class="mt-1 font-medium text-gray-900">
                                {{ comboSecondDish?.name ?? 'Не выбрано' }}
                            </p>
                            <p v-if="comboSecondDish" class="mt-0.5 text-xs text-max-muted">
                                {{ comboSecondDish.category_name }}
                            </p>
                        </div>
                    </div>

                    <div class="mt-4 flex items-center justify-between gap-3">
                        <div class="flex items-center gap-2">
                            <button
                                type="button"
                                class="flex h-9 w-9 items-center justify-center rounded-full border border-gray-200 bg-gray-50 text-lg font-medium transition hover:bg-gray-100 disabled:opacity-40"
                                :disabled="comboQuantity <= 1 || addingComboRef !== null"
                                @click="changeComboQuantity(-1)"
                            >
                                −
                            </button>
                            <span class="w-8 text-center text-sm font-semibold text-gray-900">{{ comboQuantity }}</span>
                            <button
                                type="button"
                                class="flex h-9 w-9 items-center justify-center rounded-full border border-gray-200 bg-gray-50 text-lg font-medium transition hover:bg-gray-100 disabled:opacity-40"
                                :disabled="comboQuantity >= 99 || addingComboRef !== null"
                                @click="changeComboQuantity(1)"
                            >
                                +
                            </button>
                        </div>
                        <button
                            type="button"
                            class="flex min-w-40 items-center justify-center rounded-2xl bg-max-primary px-4 py-3 text-sm font-medium text-white transition hover:bg-max-primary-hover disabled:cursor-not-allowed disabled:opacity-50"
                            :disabled="!canAddCombo"
                            @click="addComboToCart"
                        >
                            <span
                                v-if="addingComboRef"
                                class="mr-2 h-4 w-4 animate-spin rounded-full border-2 border-white border-t-transparent"
                            />
                            Добавить · {{ comboTotal }} ₽
                        </button>
                    </div>
                </section>

                <p
                    v-if="visibleCategories.length === 0"
                    class="py-16 text-center text-sm text-max-muted"
                >
                    Сейчас нет доступных блюд
                </p>

                <section v-for="category in visibleCategories" :key="category.id">
                    <h2 class="mb-3 text-base font-semibold text-gray-800">{{ category.name }}</h2>
                    <ul class="space-y-2">
                        <li
                            v-for="dish in category.dishes"
                            :key="dish.id"
                            class="flex items-center gap-3 rounded-2xl border border-gray-100 bg-white p-3 shadow-sm"
                        >
                            <DishImage :image-url="dish.image_url" :alt="dish.name" />
                            <div class="min-w-0 flex-1">
                                <p class="font-medium text-gray-900">{{ dish.name }}</p>
                                <p class="mt-0.5 text-sm font-semibold text-max-primary">{{ dish.price }} ₽</p>
                            </div>
                            <div class="flex shrink-0 items-center gap-1.5">
                                <button
                                    v-if="categoryAllowsCombo(category) && !comboBuilderOpen"
                                    type="button"
                                    class="rounded-full border border-max-primary px-2 py-1 text-xs font-medium text-max-primary transition hover:bg-max-primary/10 disabled:cursor-not-allowed disabled:opacity-40"
                                    :disabled="addingComboRef !== null"
                                    @click="startComboBuilder(dish, category)"
                                >
                                    собрать комбо
                                </button>
                                <button
                                    v-if="canSelectAsSecondDish(dish, category)"
                                    type="button"
                                    class="flex h-9 min-w-9 items-center justify-center rounded-full border border-max-primary bg-white px-2 text-xs font-medium text-max-primary transition hover:bg-max-primary/10 disabled:cursor-not-allowed disabled:opacity-40"
                                    :disabled="addingComboRef !== null"
                                    @click="selectSecondComboDish(dish, category)"
                                >
                                    {{ comboSecondDish?.id === dish.id ? '2' : 'В комбо' }}
                                </button>
                                <button
                                    type="button"
                                    class="flex h-9 min-w-9 items-center justify-center rounded-full bg-max-primary px-3 text-sm font-medium text-white transition hover:bg-max-primary-hover disabled:cursor-not-allowed disabled:opacity-40"
                                    :disabled="addingDishId === dish.id || addingComboRef !== null"
                                    @click="emit('add-to-cart', dish)"
                                >
                                    <span
                                        v-if="addingDishId === dish.id"
                                        class="h-4 w-4 animate-spin rounded-full border-2 border-white border-t-transparent"
                                    />
                                    <span v-else>+</span>
                                </button>
                            </div>
                        </li>
                    </ul>
                </section>
            </div>
        </main>

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
