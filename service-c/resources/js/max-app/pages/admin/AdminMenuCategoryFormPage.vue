<script setup>
/**
 * Форма создания и редактирования категории меню.
 */
import { computed, ref, watch } from 'vue';
import AppSelect from '../../components/AppSelect.vue';

/** @typedef {import('../../api/types.js').MenuCategoryAvailabilityOffsetDto} MenuCategoryAvailabilityOffsetDto */

const WEEKDAY_OPTIONS = [
    { value: 1, label: 'Пн' },
    { value: 2, label: 'Вт' },
    { value: 3, label: 'Ср' },
    { value: 4, label: 'Чт' },
    { value: 5, label: 'Пт' },
    { value: 6, label: 'Сб' },
    { value: 7, label: 'Вс' },
];

/**
 * @typedef {object} AvailabilityOffsetRuleForm
 * @property {number[]} weekdays
 * @property {string} offset_days
 */

const props = defineProps({
    category: {
        type: Object,
        default: null,
    },
    restaurantOptions: {
        type: Array,
        default: () => [],
    },
    loading: {
        type: Boolean,
        default: false,
    },
    submitLoading: {
        type: Boolean,
        default: false,
    },
    error: {
        type: String,
        default: '',
    },
    serverFieldErrors: {
        type: Object,
        default: () => ({}),
    },
});

const emit = defineEmits(['back', 'submit']);

const isEditMode = computed(() => Boolean(props.category?.id));

const restaurantId = ref('');
const name = ref('');
const sortOrder = ref('0');
const isComboAvailable = ref(true);
/** @type {import('vue').Ref<AvailabilityOffsetRuleForm[]>} */
const availabilityOffsetRules = ref([]);

const fieldErrors = ref({});

const displayFieldErrors = computed(() => ({
    ...props.serverFieldErrors,
    ...fieldErrors.value,
}));

const pageTitle = computed(() => (isEditMode.value ? 'Редактирование категории' : 'Новая категория'));

const restaurantSelectOptions = computed(() => [
    { value: '', label: 'Выберите ресторан', disabled: true },
    ...props.restaurantOptions.map((restaurant) => ({
        value: String(restaurant.id),
        label: restaurant.name,
    })),
]);

/** Дни недели, уже занятые хотя бы в одном правиле. */
const occupiedWeekdays = computed(() => {
    /** @type {Set<number>} */
    const occupied = new Set();

    for (const rule of availabilityOffsetRules.value) {
        for (const weekday of rule.weekdays) {
            occupied.add(weekday);
        }
    }

    return occupied;
});

const canAddOffsetRule = computed(() => occupiedWeekdays.value.size < WEEKDAY_OPTIONS.length);

/**
 * @param {MenuCategoryAvailabilityOffsetDto[]|undefined|null} offsets
 * @returns {AvailabilityOffsetRuleForm[]}
 */
function mapOffsetsFromCategory(offsets) {
    if (!Array.isArray(offsets)) {
        return [];
    }

    return offsets.map((rule) => ({
        weekdays: Array.isArray(rule.weekdays)
            ? [...rule.weekdays].map(Number).filter((day) => day >= 1 && day <= 7).sort((a, b) => a - b)
            : [],
        offset_days: rule.offset_days !== undefined && rule.offset_days !== null
            ? String(rule.offset_days)
            : '0',
    }));
}

function resetForm() {
    restaurantId.value = props.category?.restaurant_id ? String(props.category.restaurant_id) : '';
    name.value = props.category?.name ?? '';
    sortOrder.value = props.category?.sort_order !== undefined ? String(props.category.sort_order) : '0';
    isComboAvailable.value = props.category?.is_combo_available ?? true;
    availabilityOffsetRules.value = mapOffsetsFromCategory(props.category?.availability_offsets);
    fieldErrors.value = {};
}

watch(
    () => props.category,
    () => {
        resetForm();
    },
    { immediate: true },
);

function addOffsetRule() {
    if (!canAddOffsetRule.value) {
        return;
    }

    availabilityOffsetRules.value.push({
        weekdays: [],
        offset_days: '0',
    });
}

/**
 * @param {number} index
 */
function removeOffsetRule(index) {
    availabilityOffsetRules.value.splice(index, 1);
}

/**
 * День занят в другом правиле (для текущего — disabled).
 *
 * @param {number} ruleIndex
 * @param {number} weekday
 * @returns {boolean}
 */
function isWeekdayTakenByOtherRule(ruleIndex, weekday) {
    return availabilityOffsetRules.value.some(
        (rule, index) => index !== ruleIndex && rule.weekdays.includes(weekday),
    );
}

/**
 * @param {number} ruleIndex
 * @param {number} weekday
 */
function toggleWeekday(ruleIndex, weekday) {
    const rule = availabilityOffsetRules.value[ruleIndex];

    if (!rule || isWeekdayTakenByOtherRule(ruleIndex, weekday)) {
        return;
    }

    if (rule.weekdays.includes(weekday)) {
        rule.weekdays = rule.weekdays.filter((day) => day !== weekday);
    } else {
        rule.weekdays = [...rule.weekdays, weekday].sort((a, b) => a - b);
    }
}

/**
 * @param {number} index
 * @param {string} field
 * @returns {string|undefined}
 */
function offsetRuleFieldError(index, field) {
    return displayFieldErrors.value[`availability_offsets.${index}.${field}`];
}

/**
 * Пересечения дней между правилами (клиентская проверка).
 *
 * @returns {boolean}
 */
function hasWeekdayIntersections() {
    /** @type {Set<number>} */
    const seen = new Set();

    for (const rule of availabilityOffsetRules.value) {
        for (const weekday of rule.weekdays) {
            if (seen.has(weekday)) {
                return true;
            }

            seen.add(weekday);
        }
    }

    return false;
}

function validateForm() {
    const errors = {};

    if (!restaurantId.value) {
        errors.restaurant_id = 'Выберите ресторан.';
    }

    if (!name.value.trim()) {
        errors.name = 'Укажите название категории.';
    }

    const parsedSortOrder = Number.parseInt(sortOrder.value, 10);

    if (!Number.isFinite(parsedSortOrder) || parsedSortOrder < 0) {
        errors.sort_order = 'Укажите корректный порядок сортировки.';
    }

    if (hasWeekdayIntersections()) {
        errors.availability_offsets = 'Один день недели нельзя указать в нескольких правилах.';
    }

    availabilityOffsetRules.value.forEach((rule, index) => {
        if (rule.weekdays.length === 0) {
            errors[`availability_offsets.${index}.weekdays`] = 'Выберите хотя бы один день недели.';
        }

        const parsedOffset = Number.parseInt(rule.offset_days, 10);

        if (!Number.isFinite(parsedOffset) || parsedOffset < 0 || parsedOffset > 30) {
            errors[`availability_offsets.${index}.offset_days`] = 'Укажите смещение от 0 до 30 дней.';
        }
    });

    fieldErrors.value = errors;

    return Object.keys(errors).length === 0;
}

function handleSubmit() {
    if (!validateForm()) {
        return;
    }

    /** @type {MenuCategoryAvailabilityOffsetDto[]} */
    const availabilityOffsets = availabilityOffsetRules.value.map((rule) => ({
        weekdays: [...rule.weekdays],
        offset_days: Number.parseInt(rule.offset_days, 10),
    }));

    const fields = {
        restaurant_id: Number(restaurantId.value),
        name: name.value.trim(),
        is_combo_available: isComboAvailable.value,
        availability_offsets: availabilityOffsets,
    };

    if (isEditMode.value) {
        fields.sort_order = Number.parseInt(sortOrder.value, 10);
    }

    emit('submit', fields);
}
</script>

<template>
    <div class="flex min-h-0 flex-1 flex-col overflow-hidden">
        <header class="shrink-0 border-b border-gray-200 bg-white px-4 py-3">
            <div class="flex items-center gap-3">
                <button
                    type="button"
                    class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-gray-600 transition hover:bg-gray-100"
                    aria-label="Назад"
                    @click="emit('back')"
                >
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
                    </svg>
                </button>
                <h1 class="min-w-0 truncate text-lg font-semibold text-gray-900">{{ pageTitle }}</h1>
            </div>
        </header>

        <div class="max-app-scroll-viewport flex-1 px-4 py-4">
            <div v-if="loading" class="flex items-center justify-center py-16">
                <div class="h-8 w-8 animate-spin rounded-full border-4 border-max-primary border-t-transparent" />
            </div>

            <form v-else class="space-y-4" @submit.prevent="handleSubmit">
                <div
                    v-if="error"
                    class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"
                >
                    {{ error }}
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">Ресторан</label>
                    <AppSelect
                        v-model="restaurantId"
                        :options="restaurantSelectOptions"
                        :disabled="isEditMode && (category?.dishes_count ?? 0) > 0"
                    />
                    <p v-if="displayFieldErrors.restaurant_id" class="mt-1 text-sm text-red-600">
                        {{ displayFieldErrors.restaurant_id }}
                    </p>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">Название</label>
                    <input
                        v-model="name"
                        type="text"
                        maxlength="255"
                        class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:border-max-primary focus:outline-none focus:ring-1 focus:ring-max-primary"
                    >
                    <p v-if="displayFieldErrors.name" class="mt-1 text-sm text-red-600">
                        {{ displayFieldErrors.name }}
                    </p>
                </div>

                <div v-if="isEditMode">
                    <label class="mb-1 block text-sm font-medium text-gray-700">Порядок сортировки</label>
                    <input
                        v-model="sortOrder"
                        type="number"
                        min="0"
                        max="65535"
                        class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:border-max-primary focus:outline-none focus:ring-1 focus:ring-max-primary"
                    >
                    <p v-if="displayFieldErrors.sort_order" class="mt-1 text-sm text-red-600">
                        {{ displayFieldErrors.sort_order }}
                    </p>
                </div>

                <label class="flex items-center gap-3 rounded-xl border border-gray-200 bg-white px-4 py-3">
                    <input
                        v-model="isComboAvailable"
                        type="checkbox"
                        class="h-4 w-4 rounded border-gray-300 text-max-primary focus:ring-max-primary"
                    >
                    <span class="text-sm text-gray-800">Доступна в режиме «Комбо»</span>
                </label>

                <section class="space-y-3 rounded-xl border border-gray-200 bg-white px-4 py-3">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <h2 class="text-sm font-medium text-gray-900">Смещение доступности блюд</h2>
                            <p class="mt-0.5 text-xs text-gray-500">
                                На сколько дней сдвигать доступность блюд в выбранные дни недели (0–30).
                            </p>
                        </div>
                        <button
                            type="button"
                            class="shrink-0 rounded-lg px-2.5 py-1.5 text-xs font-medium text-max-primary transition hover:bg-max-primary/10 disabled:cursor-not-allowed disabled:opacity-40"
                            :disabled="!canAddOffsetRule"
                            @click="addOffsetRule"
                        >
                            Добавить
                        </button>
                    </div>

                    <p v-if="displayFieldErrors.availability_offsets" class="text-sm text-red-600">
                        {{ displayFieldErrors.availability_offsets }}
                    </p>

                    <p
                        v-if="availabilityOffsetRules.length === 0"
                        class="text-sm text-gray-500"
                    >
                        Правила не заданы — смещение не применяется.
                    </p>

                    <div
                        v-for="(rule, ruleIndex) in availabilityOffsetRules"
                        :key="ruleIndex"
                        class="space-y-3 rounded-lg border border-gray-100 bg-gray-50 px-3 py-3"
                    >
                        <div class="flex items-center justify-between gap-2">
                            <span class="text-xs font-medium text-gray-600">Правило {{ ruleIndex + 1 }}</span>
                            <button
                                type="button"
                                class="text-xs font-medium text-red-600 transition hover:text-red-700"
                                @click="removeOffsetRule(ruleIndex)"
                            >
                                Удалить
                            </button>
                        </div>

                        <div class="flex items-start gap-3">
                            <div class="min-w-0 flex-1">
                                <p class="mb-2 text-xs font-medium text-gray-700">Дни недели</p>
                                <div class="flex flex-wrap gap-2">
                                    <label
                                        v-for="day in WEEKDAY_OPTIONS"
                                        :key="day.value"
                                        class="inline-flex items-center gap-1.5 rounded-lg border px-2.5 py-1.5 text-xs transition"
                                        :class="[
                                            rule.weekdays.includes(day.value)
                                                ? 'border-max-primary bg-max-primary/10 text-max-primary'
                                                : 'border-gray-200 bg-white text-gray-700',
                                            isWeekdayTakenByOtherRule(ruleIndex, day.value)
                                                ? 'cursor-not-allowed opacity-40'
                                                : 'cursor-pointer hover:border-gray-300',
                                        ]"
                                    >
                                        <input
                                            type="checkbox"
                                            class="sr-only"
                                            :checked="rule.weekdays.includes(day.value)"
                                            :disabled="isWeekdayTakenByOtherRule(ruleIndex, day.value)"
                                            @change="toggleWeekday(ruleIndex, day.value)"
                                        >
                                        {{ day.label }}
                                    </label>
                                </div>
                                <p
                                    v-if="offsetRuleFieldError(ruleIndex, 'weekdays')"
                                    class="mt-1 text-sm text-red-600"
                                >
                                    {{ offsetRuleFieldError(ruleIndex, 'weekdays') }}
                                </p>
                            </div>

                            <div class="w-24 shrink-0">
                                <label class="mb-2 block text-xs font-medium text-gray-700">Смещение, дней</label>
                                <input
                                    v-model="rule.offset_days"
                                    type="number"
                                    min="0"
                                    max="30"
                                    class="w-full rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm focus:border-max-primary focus:outline-none focus:ring-1 focus:ring-max-primary"
                                >
                                <p
                                    v-if="offsetRuleFieldError(ruleIndex, 'offset_days')"
                                    class="mt-1 text-sm text-red-600"
                                >
                                    {{ offsetRuleFieldError(ruleIndex, 'offset_days') }}
                                </p>
                            </div>
                        </div>
                    </div>
                </section>

                <button
                    type="submit"
                    class="w-full rounded-2xl bg-max-primary px-4 py-3 text-sm font-medium text-white transition hover:bg-max-primary-hover disabled:cursor-not-allowed disabled:opacity-50"
                    :disabled="submitLoading"
                >
                    {{ submitLoading ? 'Сохранение…' : 'Сохранить' }}
                </button>
            </form>
        </div>
    </div>
</template>
