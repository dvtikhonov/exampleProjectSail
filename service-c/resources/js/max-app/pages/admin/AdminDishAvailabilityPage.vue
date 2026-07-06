<script setup>
/**
 * График доступности блюд: таблица дата × блюдо с редактированием будущих дат.
 */
import { computed } from 'vue';
import AppSelect from '../../components/AppSelect.vue';

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
});

const emit = defineEmits([
    'filter-restaurant',
    'filter-category',
    'filter-name-search',
    'toggle',
    'save',
    'refresh',
]);

const restaurantSelectOptions = computed(() => [
    { value: '', label: 'Выберите ресторан', disabled: true },
    ...props.restaurantOptions.map((restaurant) => ({
        value: String(restaurant.id),
        label: restaurant.name,
    })),
]);

const visibleDates = computed(() => props.dates.filter((date) => props.isDateEditable(date)));

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
 * @param {number} dishId
 * @param {string} date
 */
function onCellClick(dishId, date) {
    if (!props.isDateEditable(date)) {
        return;
    }

    emit('toggle', dishId, date);
}
</script>

<template>
    <div class="flex h-full min-h-0 flex-col">
        <div class="shrink-0 space-y-3 border-b border-gray-100 px-4 py-3">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <h1 class="text-lg font-semibold text-gray-900">График доступности</h1>
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
            class="px-4 pb-4 pt-3 text-center text-sm text-max-muted"
        >
            <div class="flex items-center justify-center py-16">
                Выберите ресторан и категорию для просмотра графика
            </div>
        </div>

        <div
            v-else-if="loading && dishes.length === 0"
            class="flex flex-1 items-center justify-center px-4 pb-4 pt-3"
        >
            <div class="h-8 w-8 animate-spin rounded-full border-4 border-max-primary border-t-transparent" />
        </div>

        <div
            v-else-if="error"
            class="px-4 pb-4 pt-3"
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
            class="px-4 pb-4 pt-3 text-center text-sm text-max-muted"
        >
            <div class="py-16">
                <template v-if="filterNameSearch.trim()">
                    По запросу «{{ filterNameSearch.trim() }}» ничего не найдено
                </template>
                <template v-else>
                    Блюда не найдены
                </template>
            </div>
        </div>

        <div
            v-else
            class="min-h-0 flex-1 overflow-auto px-4 pb-4 pt-3 touch-pan-x touch-pan-y"
        >
            <div class="w-max rounded-2xl border border-gray-100 bg-white shadow-sm">
                <table class="border-collapse text-sm">
                    <thead class="sticky top-0 z-10 bg-gray-50">
                        <tr>
                            <th
                                scope="col"
                                class="sticky left-0 z-20 w-[9rem] border-b border-r border-gray-200 bg-gray-50 px-3 py-2 text-left text-xs font-semibold text-gray-700"
                            >
                                Блюдо
                            </th>
                            <th
                                v-for="date in visibleDates"
                                :key="date"
                                scope="col"
                                class="w-[3.25rem] whitespace-nowrap border-b border-gray-200 px-1 py-2 text-center text-xs font-medium text-gray-700"
                            >
                                <span class="block leading-tight">{{ formatDateLabel(date) }}</span>
                                <span class="block text-[10px] font-normal uppercase leading-tight text-max-muted">
                                    {{ formatWeekdayLabel(date) }}
                                </span>
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
                                class="sticky left-0 z-10 w-[9rem] border-r border-gray-100 bg-white px-3 py-2 text-left text-xs font-medium text-gray-900"
                            >
                                <span class="line-clamp-2">{{ dish.name }}</span>
                            </th>
                            <td
                                v-for="date in visibleDates"
                                :key="`${dish.id}-${date}`"
                                class="w-[3.25rem] px-1 py-2 text-center"
                            >
                                <button
                                    type="button"
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
    </div>
</template>
