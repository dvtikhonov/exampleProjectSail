<script setup>
/**
 * Форма создания и редактирования категории меню.
 */
import { computed, ref, watch } from 'vue';
import AppSelect from '../../components/AppSelect.vue';

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

function resetForm() {
    restaurantId.value = props.category?.restaurant_id ? String(props.category.restaurant_id) : '';
    name.value = props.category?.name ?? '';
    sortOrder.value = props.category?.sort_order !== undefined ? String(props.category.sort_order) : '0';
    isComboAvailable.value = props.category?.is_combo_available ?? true;
    fieldErrors.value = {};
}

watch(
    () => props.category,
    () => {
        resetForm();
    },
    { immediate: true },
);

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

    fieldErrors.value = errors;

    return Object.keys(errors).length === 0;
}

function handleSubmit() {
    if (!validateForm()) {
        return;
    }

    const fields = {
        restaurant_id: Number(restaurantId.value),
        name: name.value.trim(),
        is_combo_available: isComboAvailable.value,
    };

    if (isEditMode.value) {
        fields.sort_order = Number.parseInt(sortOrder.value, 10);
    }

    emit('submit', fields);
}
</script>

<template>
    <div class="flex min-h-0 flex-1 flex-col overflow-hidden">
        <header class="shrink-0 border-b border-gray-100 px-4 py-3">
            <button
                type="button"
                class="mb-2 text-sm font-medium text-max-primary"
                @click="emit('back')"
            >
                ← Назад
            </button>
            <h1 class="text-lg font-semibold text-gray-900">{{ pageTitle }}</h1>
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
