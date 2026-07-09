<script setup>
/**
 * Список блюд для администратора: фильтры, добавление, редактирование, удаление.
 */
import { computed, nextTick, onActivated, onDeactivated, onUnmounted, ref, watch } from 'vue';
import AppSelect from '../../components/AppSelect.vue';
import DishImage from '../../components/DishImage.vue';
import { useScrollViewport } from '../../composables/useScrollViewport';

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
    filterCategoryId: {
        type: String,
        default: '',
    },
    filterNameSearch: {
        type: String,
        default: '',
    },
    importLoading: {
        type: Boolean,
        default: false,
    },
    importError: {
        type: String,
        default: '',
    },
    importSuccessMessage: {
        type: String,
        default: '',
    },
    testBotLoading: {
        type: Boolean,
        default: false,
    },
    testBotError: {
        type: String,
        default: '',
    },
    testBotSuccessMessage: {
        type: String,
        default: '',
    },
    testBot2Loading: {
        type: Boolean,
        default: false,
    },
    testBot2Error: {
        type: String,
        default: '',
    },
    testBot2SuccessMessage: {
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
    'import-click',
    'import',
    'test-bot',
    'test-bot-2',
]);

const fileInputRef = ref(null);
const listViewportRef = ref(null);

const {
    refreshViewport,
    readScrollTop,
    applyScrollTop,
} = useScrollViewport(listViewportRef, { enableTouchScroll: true });

const pullDistance = ref(0);
const isPulling = ref(false);
let touchStartY = 0;
/** Сохранённая позиция прокрутки при переходе к форме редактирования */
let savedScrollTop = 0;

/** Порог смещения пальца (px) для срабатывания обновления списка */
const PULL_THRESHOLD = 72;

const restaurantSelectOptions = computed(() => [
    { value: '', label: 'Все рестораны' },
    ...props.restaurantOptions.map((restaurant) => ({
        value: String(restaurant.id),
        label: restaurant.name,
    })),
]);

const categorySelectOptions = computed(() => [
    { value: '', label: 'Все категории' },
    ...props.categoryOptions.map((category) => ({
        value: String(category.id),
        label: props.filterRestaurantId
            ? category.name
            : `${category.name} (${category.restaurantName})`,
    })),
]);

function onTouchStart(event) {
    if (readScrollTop() > 0 || props.loading || props.refreshing) {
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

function captureScrollPosition() {
    savedScrollTop = readScrollTop();
}

function restoreScrollPosition() {
    const apply = () => {
        if (savedScrollTop > 0) {
            applyScrollTop(savedScrollTop);
        }
    };

    nextTick(() => {
        apply();

        requestAnimationFrame(() => {
            apply();
        });
    });
}

/** @type {boolean} */
let pullListenersBound = false;

function bindPullToRefreshListeners() {
    const element = listViewportRef.value;

    if (pullListenersBound || !element) {
        return;
    }

    pullListenersBound = true;
    element.addEventListener('touchstart', onTouchStart, { passive: true });
    element.addEventListener('touchmove', onTouchMove, { passive: true });
    element.addEventListener('touchend', onTouchEnd, { passive: true });
}

function unbindPullToRefreshListeners() {
    const element = listViewportRef.value;

    if (!pullListenersBound || !element) {
        return;
    }

    pullListenersBound = false;
    element.removeEventListener('touchstart', onTouchStart);
    element.removeEventListener('touchmove', onTouchMove);
    element.removeEventListener('touchend', onTouchEnd);
}

onUnmounted(() => {
    unbindPullToRefreshListeners();
});

onActivated(() => {
    refreshViewport();
    bindPullToRefreshListeners();
    restoreScrollPosition();
});

onDeactivated(() => {
    unbindPullToRefreshListeners();
    isPulling.value = false;
    pullDistance.value = 0;
});

watch(listViewportRef, () => {
    bindPullToRefreshListeners();
    refreshViewport();
});

watch(
    () => [props.loading, props.refreshing, props.dishes.length],
    () => {
        refreshViewport();
    },
);

watch(
    () => [props.loading, props.refreshing],
    ([loading, refreshing], [prevLoading, prevRefreshing]) => {
        const wasBusy = prevLoading || prevRefreshing;
        const isBusy = loading || refreshing;

        if (wasBusy && !isBusy && savedScrollTop > 0) {
            restoreScrollPosition();
        }
    },
);

function onImportButtonClick() {
    emit('import-click');
}

function onImportFileChange(event) {
    const input = event.target;
    const file = input.files?.[0];

    if (file) {
        emit('import', file);
    }

    input.value = '';
}

function openFilePicker() {
    fileInputRef.value?.click();
}

function onEditClick(dish) {
    captureScrollPosition();
    emit('edit', dish);
}

defineExpose({ openFilePicker });
</script>

<template>
    <div class="flex min-h-0 flex-1 flex-col overflow-hidden">
        <div class="shrink-0 space-y-3 border-b border-gray-100 px-4 py-3">
            <div class="flex items-center justify-between gap-3">
                <div>
                    <h1 class="text-lg font-semibold text-gray-900">Меню</h1>
                    <p class="text-sm text-max-muted">Управление блюдами</p>
                </div>
                <div class="flex shrink-0 items-center gap-2">
                    <button
                        type="button"
                        class="rounded-xl border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700 transition hover:border-max-primary/30 hover:text-max-primary active:scale-[0.98] disabled:opacity-50"
                        :disabled="testBotLoading || testBot2Loading || importLoading"
                        @click="emit('test-bot')"
                    >
                        {{ testBotLoading ? 'Отправка…' : 'тест бот' }}
                    </button>
                    <button
                        type="button"
                        class="rounded-xl border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700 transition hover:border-max-primary/30 hover:text-max-primary active:scale-[0.98] disabled:opacity-50"
                        :disabled="testBotLoading || testBot2Loading || importLoading"
                        @click="emit('test-bot-2')"
                    >
                        {{ testBot2Loading ? 'Отправка…' : 'тест бот 2' }}
                    </button>
                    <button
                        type="button"
                        class="rounded-xl border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700 transition hover:border-max-primary/30 hover:text-max-primary active:scale-[0.98] disabled:opacity-50"
                        :disabled="importLoading || testBotLoading || testBot2Loading"
                        @click="onImportButtonClick"
                    >
                        {{ importLoading ? 'Загрузка…' : 'Загрузить' }}
                    </button>
                    <button
                        type="button"
                        class="shrink-0 rounded-xl bg-max-primary px-4 py-2 text-sm font-medium text-white transition hover:bg-max-primary/90 active:scale-[0.98]"
                        @click="emit('add')"
                    >
                        Добавить
                    </button>
                </div>
            </div>

            <input
                ref="fileInputRef"
                type="file"
                accept=".xls,.xlsx"
                class="hidden"
                @change="onImportFileChange"
            >

            <div class="grid grid-cols-2 gap-2">
                <AppSelect
                    :model-value="filterRestaurantId"
                    :options="restaurantSelectOptions"
                    size="sm"
                    @update:model-value="emit('filter-restaurant', $event)"
                />

                <AppSelect
                    :model-value="filterCategoryId"
                    :options="categorySelectOptions"
                    size="sm"
                    @update:model-value="emit('filter-category', $event)"
                />
            </div>

            <input
                type="search"
                :value="filterNameSearch"
                placeholder="Поиск по названию"
                autocomplete="off"
                class="w-full rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm focus:border-max-primary focus:outline-none focus:ring-1 focus:ring-max-primary"
                @input="emit('filter-name-search', ($event.target).value)"
            >

            <div
                v-if="testBotError"
                class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"
            >
                {{ testBotError }}
            </div>

            <div
                v-if="testBotSuccessMessage"
                class="rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700"
            >
                {{ testBotSuccessMessage }}
            </div>

            <div
                v-if="testBot2Error"
                class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"
            >
                {{ testBot2Error }}
            </div>

            <div
                v-if="testBot2SuccessMessage"
                class="rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700"
            >
                {{ testBot2SuccessMessage }}
            </div>

            <div
                v-if="importError"
                class="whitespace-pre-line rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"
            >
                {{ importError }}
            </div>

            <div
                v-if="importSuccessMessage"
                class="rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700"
            >
                {{ importSuccessMessage }}
            </div>
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
            ref="listViewportRef"
            class="max-app-scroll-viewport px-4 pb-4"
            tabindex="0"
            role="region"
            aria-label="Список блюд"
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
                                    @click="onEditClick(dish)"
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
