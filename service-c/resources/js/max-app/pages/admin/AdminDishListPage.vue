<script setup>
/**
 * Список блюд для администратора: фильтры, добавление, редактирование, удаление.
 */
import { onMounted, onUnmounted, ref } from 'vue';
import DishImage from '../../components/DishImage.vue';

const props = defineProps({
    dishes: {
        type: Array,
        default: () => [],
    },
    loading: {
        type: Boolean,
        default: false,
    },
    error: {
        type: String,
        default: '',
    },
    refreshing: {
        type: Boolean,
        default: false,
    },
    deleteError: {
        type: String,
        default: '',
    },
    deleteLoadingId: {
        type: Number,
        default: null,
    },
    restaurantOptions: {
        type: Array,
        default: () => [],
    },
    categoryOptions: {
        type: Array,
        default: () => [],
    },
    filterRestaurantId: {
        type: String,
        default: '',
    },
    filterCategoryName: {
        type: String,
        default: '',
    },
    filterNameSearch: {
        type: String,
        default: '',
    },
});

const emit = defineEmits([
    'add',
    'edit',
    'delete',
    'refresh',
    'filter-restaurant',
    'filter-category',
    'filter-name-search',
]);

const pullDistance = ref(0);
const isPulling = ref(false);
let touchStartY = 0;
let scrollContainer = null;

/** Порог смещения пальца (px) для срабатывания обновления списка */
const PULL_THRESHOLD = 72;

function onTouchStart(event) {
    if (!scrollContainer || scrollContainer.scrollTop > 0 || props.loading || props.refreshing) {
        isPulling.value = false;

        return;
    }

    touchStartY = event.touches[0].clientY;
    isPulling.value = true;
}

function onTouchMove(event) {
    if (!isPulling.value) {
        return;
    }

    const delta = event.touches[0].clientY - touchStartY;

    if (delta <= 0) {
        pullDistance.value = 0;

        return;
    }

    pullDistance.value = Math.min(delta * 0.5, PULL_THRESHOLD * 1.5);
}

function onTouchEnd() {
    if (isPulling.value && pullDistance.value >= PULL_THRESHOLD && !props.refreshing) {
        emit('refresh');
    }

    isPulling.value = false;
    pullDistance.value = 0;
}

onMounted(() => {
    scrollContainer = document.getElementById('admin-dish-list-scroll');
});

onUnmounted(() => {
    scrollContainer = null;
});
</script>

<template>
    <div class="flex min-h-0 flex-1 flex-col">
        <div class="shrink-0 space-y-3 border-b border-gray-100 px-4 py-3">
            <div class="flex items-center justify-between gap-3">
                <div>
                    <h1 class="text-lg font-semibold text-gray-900">Меню</h1>
                    <p class="text-sm text-max-muted">Управление блюдами</p>
                </div>
                <button
                    type="button"
                    class="shrink-0 rounded-xl bg-max-primary px-4 py-2 text-sm font-medium text-white transition hover:bg-max-primary/90 active:scale-[0.98]"
                    @click="emit('add')"
                >
                    Добавить
                </button>
            </div>

            <div class="grid grid-cols-2 gap-2">
                <select
                    :value="filterRestaurantId"
                    class="rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm focus:border-max-primary focus:outline-none focus:ring-1 focus:ring-max-primary"
                    @change="emit('filter-restaurant', ($event.target).value)"
                >
                    <option value="">Все рестораны</option>
                    <option
                        v-for="restaurant in restaurantOptions"
                        :key="restaurant.id"
                        :value="String(restaurant.id)"
                    >
                        {{ restaurant.name }}
                    </option>
                </select>

                <select
                    :value="filterCategoryName"
                    class="rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm focus:border-max-primary focus:outline-none focus:ring-1 focus:ring-max-primary"
                    @change="emit('filter-category', ($event.target).value)"
                >
                    <option value="">Все категории</option>
                    <option
                        v-for="category in categoryOptions"
                        :key="category.name"
                        :value="category.name"
                    >
                        {{ category.name }}
                    </option>
                </select>
            </div>

            <input
                type="search"
                :value="filterNameSearch"
                placeholder="Поиск по названию"
                autocomplete="off"
                class="w-full rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm focus:border-max-primary focus:outline-none focus:ring-1 focus:ring-max-primary"
                @input="emit('filter-name-search', ($event.target).value)"
            >
        </div>

        <div
            class="flex shrink-0 items-center justify-center overflow-hidden text-xs text-max-muted transition-[height]"
            :style="{ height: `${Math.max(pullDistance, refreshing ? 40 : 0)}px` }"
        >
            <span v-if="refreshing">Обновление…</span>
            <span v-else-if="pullDistance >= PULL_THRESHOLD">Отпустите для обновления</span>
            <span v-else-if="pullDistance > 0">Потяните вниз</span>
        </div>

        <div
            id="admin-dish-list-scroll"
            class="min-h-0 flex-1 overflow-y-auto px-4 pb-4"
            @touchstart.passive="onTouchStart"
            @touchmove.passive="onTouchMove"
            @touchend="onTouchEnd"
        >
            <div
                v-if="deleteError"
                class="mb-3 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"
            >
                {{ deleteError }}
            </div>

            <div v-if="loading && dishes.length === 0" class="flex items-center justify-center py-16">
                <div class="h-8 w-8 animate-spin rounded-full border-4 border-max-primary border-t-transparent" />
            </div>

            <div
                v-else-if="error"
                class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"
            >
                {{ error }}
                <button
                    type="button"
                    class="mt-2 block font-medium text-red-800 underline"
                    @click="emit('refresh')"
                >
                    Повторить
                </button>
            </div>

            <div v-else-if="dishes.length === 0" class="py-16 text-center text-sm text-max-muted">
                Блюда не найдены
            </div>

            <ul v-else class="space-y-3">
                <li
                    v-for="dish in dishes"
                    :key="dish.id"
                    class="rounded-2xl border border-gray-100 bg-white p-4 shadow-sm"
                >
                    <div class="flex gap-3">
                        <DishImage
                            :image-url="dish.image_url"
                            :alt="dish.name"
                            size="md"
                        />

                        <div class="min-w-0 flex-1">
                            <div class="flex items-start justify-between gap-2">
                                <div class="min-w-0">
                                    <p class="break-words text-sm font-semibold text-gray-900">{{ dish.name }}</p>
                                    <p class="mt-0.5 text-sm text-max-muted">
                                        {{ dish.weight }} {{ dish.weight_unit_label }}
                                    </p>
                                </div>
                                <div class="shrink-0 text-right text-sm">
                                    <p class="font-semibold text-gray-900">{{ dish.price }} ₽</p>
                                    <p class="mt-0.5 text-xs text-max-muted">{{ dish.vat_rate_label }}</p>
                                </div>
                            </div>

                            <p class="mt-1 truncate text-sm text-max-muted">
                                {{ dish.menu_category_name }}
                            </p>

                            <p
                                class="mt-1 text-xs font-medium"
                                :class="dish.is_available ? 'text-green-600' : 'text-red-600'"
                            >
                                {{ dish.is_available ? 'Доступно' : 'Скрыто' }}
                            </p>

                            <div class="mt-3 flex gap-2">
                                <button
                                    type="button"
                                    class="rounded-lg border border-gray-200 px-3 py-1.5 text-sm font-medium text-gray-700 transition hover:border-max-primary/30 hover:text-max-primary"
                                    @click="emit('edit', dish)"
                                >
                                    Редактировать
                                </button>
                                <button
                                    type="button"
                                    class="rounded-lg border border-red-200 px-3 py-1.5 text-sm font-medium text-red-700 transition hover:bg-red-50 disabled:opacity-50"
                                    :disabled="deleteLoadingId === dish.id"
                                    @click="emit('delete', dish)"
                                >
                                    {{ deleteLoadingId === dish.id ? 'Удаление…' : 'Удалить' }}
                                </button>
                            </div>
                        </div>
                    </div>
                </li>
            </ul>
        </div>
    </div>
</template>
