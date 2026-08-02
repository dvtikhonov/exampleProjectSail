<script setup>
/**
 * График производства блюд: таблица дата × блюдо с редактированием будущих дат.
 */
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import AppSelect from '../../components/AppSelect.vue';
import { useScrollViewport } from '../../composables/useScrollViewport';

const props = defineProps({
    dishes: {
        type: Array,
        default: () => [],
    },
    dates: {
        type: Array,
        default: () => [],
    },
    editableFrom: {
        type: String,
        default: '',
    },
    loading: {
        type: Boolean,
        default: false,
    },
    saving: {
        type: Boolean,
        default: false,
    },
    error: {
        type: String,
        default: '',
    },
    saveError: {
        type: String,
        default: '',
    },
    filtersReady: {
        type: Boolean,
        default: false,
    },
    hasUnsavedChanges: {
        type: Boolean,
        default: false,
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
    isDateEditable: {
        type: Function,
        required: true,
    },
    isAvailable: {
        type: Function,
        required: true,
    },
    /**
     * Локальный график: dishId → даты доступности.
     * Нужен для реактивного пересчёта бейджей в заголовках дат.
     */
    schedule: {
        type: Object,
        default: () => ({}),
    },
});

const emit = defineEmits([
    'filter-restaurant',
    'filter-category',
    'filter-name-search',
    'toggle',
    'save',
    'refresh',
]);

const gridViewportRef = ref(null);
const openDishNameId = ref(null);
/** @type {import('vue').Ref<string|null>} */
const openDateTooltip = ref(null);
const { refreshViewport } = useScrollViewport(gridViewportRef, { autoFocus: true });

const restaurantSelectOptions = computed(() => [
    { value: '', label: 'Выберите ресторан', disabled: true },
    ...props.restaurantOptions.map((restaurant) => ({
        value: String(restaurant.id),
        label: restaurant.name,
    })),
]);

const visibleDates = computed(() => props.dates.filter((date) => props.isDateEditable(date)));

/**
 * Блюда, выбранные на дату (по текущему фильтру списка).
 *
 * @returns {Record<string, { id: number, name: string }[]>}
 */
const selectedDishesByDate = computed(() => {
    /** @type {Record<string, { id: number, name: string }[]>} */
    const result = {};
    const schedule = props.schedule;

    for (const date of visibleDates.value) {
        result[date] = props.dishes.filter((dish) => {
            const dishDates = schedule[String(dish.id)] ?? [];

            return dishDates.includes(date);
        });
    }

    return result;
});

const categorySelectOptions = computed(() => {
    if (!props.filterRestaurantId) {
        return [{ value: '', label: 'Сначала выберите ресторан', disabled: true }];
    }

    return [
        { value: '', label: 'Выберите категорию', disabled: true },
        ...props.categoryOptions.map((category) => ({
            value: String(category.id),
            label: category.name,
        })),
    ];
});

const dateFormatter = new Intl.DateTimeFormat('ru-RU', {
    day: '2-digit',
    month: '2-digit',
});

const weekdayFormatter = new Intl.DateTimeFormat('ru-RU', {
    weekday: 'short',
});

/**
 * @param {string} date
 * @returns {string}
 */
function formatDateLabel(date) {
    const parsed = parseIsoDate(date);

    if (!parsed) {
        return date;
    }

    return dateFormatter.format(parsed);
}

/**
 * @param {string} date
 * @returns {string}
 */
function formatWeekdayLabel(date) {
    const parsed = parseIsoDate(date);

    if (!parsed) {
        return '';
    }

    return weekdayFormatter.format(parsed);
}

/**
 * @param {string} date
 * @returns {Date|null}
 */
function parseIsoDate(date) {
    const parts = date.split('-').map(Number);

    if (parts.length !== 3 || parts.some(Number.isNaN)) {
        return null;
    }

    return new Date(parts[0], parts[1] - 1, parts[2]);
}

/**
 * @param {string} date
 * @returns {{ id: number, name: string }[]}
 */
function dishesForDate(date) {
    return selectedDishesByDate.value[date] ?? [];
}

/**
 * @param {string} date
 * @returns {number}
 */
function countForDate(date) {
    return dishesForDate(date).length;
}

/**
 * @param {number} count
 * @returns {string}
 */
function formatBadgeCount(count) {
    return count > 99 ? '99+' : String(count);
}

/**
 * @param {number} dishId
 * @param {string} date
 */
function onCellClick(dishId, date) {
    closeTooltips();

    if (!props.isDateEditable(date)) {
        return;
    }

    emit('toggle', dishId, date);
}

/**
 * @param {number} dishId
 * @param {MouseEvent} event
 */
function toggleDishNameTooltip(dishId, event) {
    event.stopPropagation();
    openDateTooltip.value = null;
    openDishNameId.value = openDishNameId.value === dishId ? null : dishId;
}

/**
 * @param {string} date
 * @param {MouseEvent} event
 */
function toggleDateTooltip(date, event) {
    event.stopPropagation();

    if (countForDate(date) === 0) {
        return;
    }

    openDishNameId.value = null;
    openDateTooltip.value = openDateTooltip.value === date ? null : date;
}

function closeTooltips() {
    openDishNameId.value = null;
    openDateTooltip.value = null;
}

/**
 * @param {KeyboardEvent} event
 */
function onDocumentKeydown(event) {
    if (event.key === 'Escape') {
        closeTooltips();
    }
}

/**
 * @param {Event} event
 */
function onDocumentScroll(event) {
    const target = event.target;

    if (target instanceof Element && target.closest('[role="tooltip"]')) {
        return;
    }

    closeTooltips();
}

/**
 * @param {FocusEvent} event
 */
function onGridFocusIn(event) {
    const target = event.target;

    if (target instanceof HTMLButtonElement) {
        target.blur();
        gridViewportRef.value?.focus({ preventScroll: true });
    }
}

onMounted(() => {
    document.addEventListener('click', closeTooltips);
    document.addEventListener('keydown', onDocumentKeydown);
    document.addEventListener('scroll', onDocumentScroll, true);
});

onUnmounted(() => {
    document.removeEventListener('click', closeTooltips);
    document.removeEventListener('keydown', onDocumentKeydown);
    document.removeEventListener('scroll', onDocumentScroll, true);
});

watch(
    [
        () => props.dishes.length,
        () => props.loading,
        () => props.filtersReady,
        () => visibleDates.value.length,
    ],
    () => {
        closeTooltips();
        refreshViewport();
    },
);

watch(selectedDishesByDate, (map) => {
    const openDate = openDateTooltip.value;

    if (openDate && (map[openDate]?.length ?? 0) === 0) {
        openDateTooltip.value = null;
    }
});
</script>

<template>
    <div class="flex min-h-0 flex-1 flex-col overflow-hidden">
        <div class="shrink-0 space-y-3 border-b border-gray-100 px-4 py-3">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <h1 class="text-lg font-semibold text-gray-900">График производства блюд</h1>
                    <p class="text-sm text-max-muted">Планирование по датам</p>
                </div>
                <button
                    type="button"
                    class="shrink-0 rounded-xl px-4 py-2 text-sm font-medium transition active:scale-[0.98] disabled:cursor-not-allowed disabled:opacity-50"
                    :class="
                        hasUnsavedChanges
                            ? 'bg-max-primary text-white hover:bg-max-primary/90'
                            : 'border border-gray-200 bg-white text-gray-400'
                    "
                    :disabled="!hasUnsavedChanges || saving || !filtersReady"
                    @click="emit('save')"
                >
                    {{ saving ? 'Сохранение…' : 'Сохранить' }}
                </button>
            </div>

            <p
                v-if="hasUnsavedChanges"
                class="text-xs font-medium text-amber-600"
            >
                Есть несохранённые изменения
            </p>

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
                    :disabled="!filterRestaurantId"
                    @update:model-value="emit('filter-category', $event)"
                />
            </div>

            <input
                v-if="filtersReady"
                type="search"
                :value="filterNameSearch"
                placeholder="Поиск по названию"
                autocomplete="off"
                class="w-full rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm focus:border-max-primary focus:outline-none focus:ring-1 focus:ring-max-primary"
                @input="emit('filter-name-search', ($event.target).value)"
            >

            <div
                v-if="saveError"
                class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"
            >
                {{ saveError }}
            </div>
        </div>

        <div
            v-if="!filtersReady"
            class="px-4 py-16 text-center text-sm text-max-muted"
        >
            Выберите ресторан и категорию для просмотра графика
        </div>

        <div
            v-else-if="loading && dishes.length === 0"
            class="flex flex-1 items-center justify-center"
        >
            <div class="h-8 w-8 animate-spin rounded-full border-4 border-max-primary border-t-transparent" />
        </div>

        <div
            v-else-if="error"
            class="px-4 py-3"
        >
            <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                {{ error }}
                <button
                    type="button"
                    class="mt-2 block font-medium text-red-800 underline"
                    @click="emit('refresh')"
                >
                    Повторить
                </button>
            </div>
        </div>

        <div
            v-else-if="dishes.length === 0"
            class="px-4 py-16 text-center text-sm text-max-muted"
        >
            <template v-if="filterNameSearch.trim()">
                По запросу «{{ filterNameSearch.trim() }}» ничего не найдено
            </template>
            <template v-else>
                Блюда не найдены
            </template>
        </div>

        <div
            v-else
            id="admin-dish-schedule-scroll"
            ref="gridViewportRef"
            class="max-app-scroll-viewport mx-4 mb-4 mt-3 rounded-2xl border border-gray-100 bg-white shadow-sm"
            tabindex="0"
            role="region"
            aria-label="Таблица графика производства блюд. Используйте свайп или стрелки для прокрутки."
            @focusin="onGridFocusIn"
        >
            <table class="schedule-grid-table w-max text-sm">
                <thead>
                    <tr>
                        <th
                            scope="col"
                            class="sticky left-0 top-0 z-30 w-[9rem] border-b border-r border-gray-200 bg-gray-50 px-3 py-2 text-left text-xs font-semibold text-gray-700"
                        >
                            Блюдо
                        </th>
                        <th
                            v-for="date in visibleDates"
                            :key="date"
                            scope="col"
                            class="relative sticky top-0 z-20 w-[3.25rem] border-b border-gray-200 bg-gray-50 px-1 py-2 text-center text-xs font-medium text-gray-700"
                            :class="{ 'z-40': openDateTooltip === date }"
                        >
                            <span class="block whitespace-nowrap leading-tight">{{ formatDateLabel(date) }}</span>
                            <span class="block whitespace-nowrap text-[10px] font-normal uppercase leading-tight text-max-muted">
                                {{ formatWeekdayLabel(date) }}
                            </span>
                            <button
                                v-if="countForDate(date) > 0"
                                type="button"
                                tabindex="-1"
                                class="mx-auto mt-1 flex h-4 min-w-4 items-center justify-center rounded-full bg-green-600 px-1 text-[10px] font-bold leading-none text-white transition hover:bg-green-700 active:scale-95"
                                :aria-expanded="openDateTooltip === date"
                                :aria-label="`Выбрано блюд: ${countForDate(date)}. Показать список`"
                                @click="toggleDateTooltip(date, $event)"
                            >
                                {{ formatBadgeCount(countForDate(date)) }}
                            </button>
                            <div
                                v-if="openDateTooltip === date"
                                class="absolute left-1/2 top-full z-50 mt-1.5 w-56 max-w-[min(14rem,calc(100vw-2rem))] -translate-x-1/2 overflow-hidden rounded-xl bg-gray-900 text-left shadow-lg"
                                role="tooltip"
                                @click.stop
                            >
                                <ul class="max-h-48 overflow-y-auto overflow-x-hidden text-xs font-medium leading-snug text-white">
                                    <li
                                        v-for="dish in dishesForDate(date)"
                                        :key="dish.id"
                                        class="whitespace-normal break-words border-b border-white/15 px-3 py-2 last:border-b-0"
                                    >
                                        {{ dish.name }}
                                    </li>
                                </ul>
                                <span
                                    class="absolute -top-1 left-1/2 h-2 w-2 -translate-x-1/2 rotate-45 bg-gray-900"
                                    aria-hidden="true"
                                />
                            </div>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="dish in dishes"
                        :key="dish.id"
                        class="border-b border-gray-100 last:border-b-0"
                    >
                        <th
                            scope="row"
                            class="relative sticky left-0 z-10 w-[9rem] cursor-pointer border-r border-gray-100 bg-white px-3 py-2 text-left text-xs font-medium text-gray-900"
                            :class="{ 'z-40': openDishNameId === dish.id }"
                            :aria-expanded="openDishNameId === dish.id"
                            :aria-label="`Название блюда: ${dish.name}`"
                            @click="toggleDishNameTooltip(dish.id, $event)"
                        >
                            <span class="line-clamp-2">{{ dish.name }}</span>
                            <span
                                v-if="openDishNameId === dish.id"
                                class="pointer-events-none absolute left-full top-1/2 z-50 ml-2 w-max max-w-[calc(3*3.25rem)] -translate-y-1/2 rounded-xl bg-gray-900 px-3 py-1.5 text-xs font-medium leading-snug text-white shadow-lg"
                                role="tooltip"
                            >
                                {{ dish.name }}
                                <span
                                    class="absolute right-full top-1/2 h-2 w-2 translate-x-1/2 -translate-y-1/2 rotate-45 bg-gray-900"
                                    aria-hidden="true"
                                />
                            </span>
                        </th>
                        <td
                            v-for="date in visibleDates"
                            :key="`${dish.id}-${date}`"
                            class="w-[3.25rem] bg-white px-1 py-2 text-center"
                        >
                            <button
                                type="button"
                                tabindex="-1"
                                class="mx-auto flex h-8 w-8 items-center justify-center rounded-lg border transition active:scale-95"
                                :class="
                                    isAvailable(dish.id, date)
                                        ? 'border-green-300 bg-green-50 text-green-700'
                                        : 'border-gray-200 bg-white text-gray-300 hover:border-max-primary/30'
                                "
                                :aria-label="`${isAvailable(dish.id, date) ? 'Доступно' : 'Недоступно'}: ${dish.name}, ${date}`"
                                @click="onCellClick(dish.id, date)"
                            >
                                <span v-if="isAvailable(dish.id, date)" aria-hidden="true">✓</span>
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
