<script setup>
/**
 * Форма создания и редактирования блюда с клиентской валидацией фото.
 */
import { computed, ref, watch } from 'vue';
import DishImage from '../../components/DishImage.vue';
import {
    DISH_PHOTO_ACCEPT,
    DISH_PHOTO_MAX_BYTES,
    isAllowedDishPhotoExtension,
    validateDishPhotoDimensions,
} from '../../constants/dishPhoto';

const WEIGHT_UNITS = [
    { value: 'g', label: 'г' },
    { value: 'kg', label: 'кг' },
    { value: 'ml', label: 'мл' },
    { value: 'l', label: 'л' },
];

const VAT_OPTIONS = [
    { value: '', label: 'Не облагается НДС' },
    { value: '5', label: '5%' },
    { value: '7', label: '7%' },
    { value: '10', label: '10%' },
    { value: '20', label: '20%' },
    { value: '22', label: '22%' },
];

const props = defineProps({
    dish: {
        type: Object,
        default: null,
    },
    categoryOptions: {
        type: Array,
        default: () => [],
    },
    restaurantOptions: {
        type: Array,
        default: () => [],
    },
    restaurantId: {
        type: String,
        default: '',
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

const emit = defineEmits(['back', 'submit', 'update:restaurantId']);

const isEditMode = computed(() => Boolean(props.dish?.id));

const name = ref('');
const menuCategoryId = ref('');
const description = ref('');
const weight = ref('');
const weightUnit = ref('g');
const price = ref('');
const vatRate = ref('');
const isAvailable = ref(true);

const photoFile = ref(null);
const photoPreviewUrl = ref(null);
/** URL уже сохранённого фото (из API), не сбрасывается при выборе нового файла */
const existingImageUrl = ref(null);
const photoError = ref('');
const photoInputRef = ref(null);

const fieldErrors = ref({});

const displayFieldErrors = computed(() => ({
    ...props.serverFieldErrors,
    ...fieldErrors.value,
}));

const pageTitle = computed(() => (isEditMode.value ? 'Редактирование блюда' : 'Новое блюдо'));

const previewImageUrl = computed(() => photoPreviewUrl.value ?? existingImageUrl.value ?? null);

const hasExistingPhoto = computed(() => Boolean(existingImageUrl.value));

const photoFileLabel = computed(() => {
    if (photoFile.value?.name) {
        return photoFile.value.name;
    }

    if (hasExistingPhoto.value) {
        return 'Текущее фото';
    }

    if (isEditMode.value) {
        return 'Фото не загружено';
    }

    return 'Файл не выбран';
});

/** Снимок полей при загрузке блюда — для сравнения в режиме редактирования */
const initialSnapshot = ref(null);

/**
 * @param {string|number|null|undefined} value
 * @returns {string}
 */
function normalizePrice(value) {
    if (value === '' || value == null) {
        return '';
    }

    const numeric = Number(value);

    if (Number.isNaN(numeric)) {
        return String(value);
    }

    return String(numeric);
}

/**
 * @returns {object}
 */
function buildFormSnapshot() {
    return {
        name: name.value.trim(),
        menuCategoryId: menuCategoryId.value,
        description: description.value.trim(),
        weight: weight.value,
        weightUnit: weightUnit.value,
        price: normalizePrice(price.value),
        vatRate: vatRate.value,
        isAvailable: isAvailable.value,
        restaurantId: props.restaurantId,
        hasNewPhoto: photoFile.value !== null,
    };
}

const hasFormChanges = computed(() => {
    if (!isEditMode.value || initialSnapshot.value === null) {
        return true;
    }

    const current = buildFormSnapshot();
    const initial = initialSnapshot.value;

    return (
        current.name !== initial.name
        || current.menuCategoryId !== initial.menuCategoryId
        || current.description !== initial.description
        || current.weight !== initial.weight
        || current.weightUnit !== initial.weightUnit
        || current.price !== initial.price
        || current.vatRate !== initial.vatRate
        || current.isAvailable !== initial.isAvailable
        || current.restaurantId !== initial.restaurantId
        || current.hasNewPhoto
    );
});

const submitDisabled = computed(() =>
    props.submitLoading
    || props.loading
    || (isEditMode.value && !hasFormChanges.value),
);

watch(
    () => props.restaurantId,
    (restaurantId) => {
        if (!restaurantId) {
            menuCategoryId.value = '';

            return;
        }

        const categoryStillValid = props['categoryOptions'].some(
            (category) => String(category.id) === menuCategoryId.value,
        );

        if (!categoryStillValid) {
            menuCategoryId.value = '';
        }
    },
);

watch(
    () => props.dish,
    (dish) => {
        resetFormFromDish(dish);
    },
    { immediate: true },
);

/**
 * @param {object|null} dish
 */
function resetFormFromDish(dish) {
    name.value = dish?.name ?? '';
    menuCategoryId.value = dish?.menu_category_id ? String(dish.menu_category_id) : '';
    description.value = dish?.description ?? '';
    weight.value = dish?.weight != null && dish.weight !== '' ? String(parseInt(String(dish.weight), 10)) : '';
    weightUnit.value = dish?.weight_unit ?? 'g';
    price.value = dish?.price ?? '';
    vatRate.value = dish?.vat_rate != null ? String(dish.vat_rate) : '';
    isAvailable.value = dish?.is_available ?? true;
    existingImageUrl.value = dish?.image_url ?? null;
    clearPhotoSelection(true);
    fieldErrors.value = {};
    initialSnapshot.value = dish?.id ? buildFormSnapshot() : null;
}

function clearPhotoSelection(resetInput = false) {
    if (photoPreviewUrl.value) {
        URL.revokeObjectURL(photoPreviewUrl.value);
    }

    photoFile.value = null;
    photoPreviewUrl.value = null;
    photoError.value = '';

    if (resetInput && photoInputRef.value) {
        photoInputRef.value.value = '';
    }
}

function openPhotoPicker() {
    photoInputRef.value?.click();
}

/**
 * @param {Event} event
 */
async function handlePhotoChange(event) {
    fieldErrors.value = { ...fieldErrors.value, photo: '' };
    photoError.value = '';

    const file = event.target.files?.[0];

    if (!file) {
        return;
    }

    if (photoPreviewUrl.value) {
        URL.revokeObjectURL(photoPreviewUrl.value);
        photoPreviewUrl.value = null;
    }

    photoFile.value = null;

    if (!isAllowedDishPhotoExtension(file.name)) {
        photoError.value = 'Допустимы только PNG и JPEG (JPG, JFIF и др.).';
        event.target.value = '';

        return;
    }

    if (file.size > DISH_PHOTO_MAX_BYTES) {
        photoError.value = 'Размер фотографии не должен превышать 25 МБ.';
        event.target.value = '';

        return;
    }

    try {
        await validateDishPhotoDimensions(file);
    } catch (error) {
        photoError.value = error instanceof Error ? error.message : 'Недопустимое изображение';
        event.target.value = '';

        return;
    }

    photoFile.value = file;
    photoPreviewUrl.value = URL.createObjectURL(file);
}

/**
 * @returns {boolean}
 */
function validateForm() {
    const errors = {};

    if (!name.value.trim()) {
        errors.name = 'Укажите название блюда.';
    }

    if (!props.restaurantId) {
        errors.restaurant_id = 'Выберите ресторан.';
    }

    if (!menuCategoryId.value) {
        errors.menu_category_id = 'Выберите категорию.';
    }

    const weightValue = Number(weight.value);

    if (
        weight.value === ''
        || !Number.isInteger(weightValue)
        || weightValue < 1
    ) {
        errors.weight = 'Вес должен быть целым числом больше нуля.';
    }

    const priceValue = Number(price.value);

    if (price.value === '' || Number.isNaN(priceValue) || priceValue < 0) {
        errors.price = 'Укажите корректную цену.';
    }

    if (!isEditMode.value && !photoFile.value) {
        errors.photo = 'Загрузите фотографию блюда.';
    }

    if (photoError.value) {
        errors.photo = photoError.value;
    }

    fieldErrors.value = errors;

    return Object.keys(errors).length === 0;
}

function handleSubmit() {
    if (!validateForm()) {
        return;
    }

    emit('submit', {
        name: name.value.trim(),
        menu_category_id: Number(menuCategoryId.value),
        description: description.value.trim() || null,
        weight: weight.value,
        weight_unit: weightUnit.value,
        price: price.value,
        vat_rate: vatRate.value === '' ? null : Number(vatRate.value),
        is_available: isAvailable.value,
    }, photoFile.value);
}
</script>

<template>
    <div class="flex min-h-dvh flex-col">
        <header class="sticky top-0 z-10 border-b border-gray-200 bg-white safe-area-top">
            <div class="flex items-center gap-3 px-4 py-3">
                <button
                    type="button"
                    class="rounded-lg p-1 text-gray-600 transition hover:bg-gray-100"
                    aria-label="Назад"
                    @click="emit('back')"
                >
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
                    </svg>
                </button>
                <div class="min-w-0">
                    <h1 class="truncate text-lg font-semibold text-gray-900">{{ pageTitle }}</h1>
                    <p class="text-sm text-max-muted">Заполните данные блюда</p>
                </div>
            </div>
        </header>

        <main class="flex-1 px-4 py-4">
            <div v-if="loading" class="flex items-center justify-center py-16">
                <div class="h-8 w-8 animate-spin rounded-full border-4 border-max-primary border-t-transparent" />
            </div>

            <form
                v-else
                class="space-y-4"
                @submit.prevent="handleSubmit"
            >
                <div
                    v-if="error"
                    class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"
                >
                    {{ error }}
                </div>

                <div>
                    <label for="dish-name" class="mb-1 block text-sm font-medium text-gray-700">
                        Название <span class="text-red-500">*</span>
                    </label>
                    <input
                        id="dish-name"
                        v-model="name"
                        type="text"
                        maxlength="255"
                        class="w-full rounded-xl border border-gray-200 px-3 py-2.5 text-sm focus:border-max-primary focus:outline-none focus:ring-1 focus:ring-max-primary"
                        :class="displayFieldErrors.name ? 'border-red-300' : ''"
                    >
                    <p v-if="displayFieldErrors.name" class="mt-1 text-xs text-red-600">{{ displayFieldErrors.name }}</p>
                </div>

                <div>
                    <label for="dish-restaurant" class="mb-1 block text-sm font-medium text-gray-700">
                        Ресторан <span class="text-red-500">*</span>
                    </label>
                    <select
                        id="dish-restaurant"
                        :value="restaurantId"
                        class="w-full rounded-xl border border-gray-200 bg-white px-3 py-2.5 text-sm focus:border-max-primary focus:outline-none focus:ring-1 focus:ring-max-primary"
                        :class="displayFieldErrors.restaurant_id ? 'border-red-300' : ''"
                        @change="emit('update:restaurantId', ($event.target).value)"
                    >
                        <option value="" disabled>Выберите ресторан</option>
                        <option
                            v-for="restaurant in restaurantOptions"
                            :key="restaurant.id"
                            :value="String(restaurant.id)"
                        >
                            {{ restaurant.name }}
                        </option>
                    </select>
                    <p v-if="displayFieldErrors.restaurant_id" class="mt-1 text-xs text-red-600">
                        {{ displayFieldErrors.restaurant_id }}
                    </p>
                </div>

                <div>
                    <label for="dish-category" class="mb-1 block text-sm font-medium text-gray-700">
                        Категория <span class="text-red-500">*</span>
                    </label>
                    <select
                        id="dish-category"
                        v-model="menuCategoryId"
                        class="w-full rounded-xl border border-gray-200 bg-white px-3 py-2.5 text-sm focus:border-max-primary focus:outline-none focus:ring-1 focus:ring-max-primary disabled:bg-gray-50 disabled:text-gray-400"
                        :class="displayFieldErrors.menu_category_id ? 'border-red-300' : ''"
                        :disabled="!restaurantId"
                    >
                        <option value="" disabled>
                            {{ restaurantId ? 'Выберите категорию' : 'Сначала выберите ресторан' }}
                        </option>
                        <option
                            v-for="category in categoryOptions"
                            :key="category.id"
                            :value="String(category.id)"
                        >
                            {{ category.label }}
                        </option>
                    </select>
                    <p v-if="displayFieldErrors.menu_category_id" class="mt-1 text-xs text-red-600">
                        {{ displayFieldErrors.menu_category_id }}
                    </p>
                </div>

                <div>
                    <label for="dish-description" class="mb-1 block text-sm font-medium text-gray-700">
                        Описание
                    </label>
                    <textarea
                        id="dish-description"
                        v-model="description"
                        rows="3"
                        maxlength="5000"
                        class="w-full rounded-xl border border-gray-200 px-3 py-2.5 text-sm focus:border-max-primary focus:outline-none focus:ring-1 focus:ring-max-primary"
                    />
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">
                        Фотография
                        <span v-if="!isEditMode" class="text-red-500">*</span>
                    </label>

                    <div v-if="previewImageUrl" class="mb-3">
                        <DishImage
                            :image-url="previewImageUrl"
                            :alt="name || 'Превью блюда'"
                            size="md"
                        />
                    </div>

                    <div
                        class="flex items-center gap-3 rounded-xl border border-gray-200 bg-white px-3 py-2.5"
                        :class="displayFieldErrors.photo || photoError ? 'border-red-300' : ''"
                    >
                        <input
                            ref="photoInputRef"
                            type="file"
                            :accept="DISH_PHOTO_ACCEPT"
                            class="sr-only"
                            @change="handlePhotoChange"
                        >
                        <button
                            type="button"
                            class="shrink-0 rounded-lg bg-max-primary/10 px-3 py-2 text-sm font-medium text-max-primary transition hover:bg-max-primary/20"
                            @click="openPhotoPicker"
                        >
                            Выбрать файл
                        </button>
                        <span
                            class="min-w-0 truncate text-sm"
                            :class="photoFile || hasExistingPhoto ? 'text-gray-700' : 'text-max-muted'"
                        >
                            {{ photoFileLabel }}
                        </span>
                    </div>
                    <p class="mt-1 text-xs text-max-muted">
                        PNG, JPG, JPEG, JFIF и др. — не более 25 МБ, разрешение не менее 800×600 px
                    </p>
                    <p v-if="isEditMode && hasExistingPhoto" class="mt-1 text-xs text-max-muted">
                        Оставьте поле пустым, чтобы сохранить текущее фото
                    </p>
                    <p v-if="displayFieldErrors.photo || photoError" class="mt-1 text-xs text-red-600">
                        {{ displayFieldErrors.photo || photoError }}
                    </p>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label for="dish-weight" class="mb-1 block text-sm font-medium text-gray-700">
                            Вес <span class="text-red-500">*</span>
                        </label>
                        <input
                            id="dish-weight"
                            v-model="weight"
                            type="number"
                            min="1"
                            step="1"
                            class="w-full rounded-xl border border-gray-200 px-3 py-2.5 text-sm focus:border-max-primary focus:outline-none focus:ring-1 focus:ring-max-primary"
                            :class="displayFieldErrors.weight ? 'border-red-300' : ''"
                        >
                        <p v-if="displayFieldErrors.weight" class="mt-1 text-xs text-red-600">{{ displayFieldErrors.weight }}</p>
                    </div>

                    <div>
                        <label for="dish-weight-unit" class="mb-1 block text-sm font-medium text-gray-700">
                            Единицы <span class="text-red-500">*</span>
                        </label>
                        <select
                            id="dish-weight-unit"
                            v-model="weightUnit"
                            class="w-full rounded-xl border border-gray-200 bg-white px-3 py-2.5 text-sm focus:border-max-primary focus:outline-none focus:ring-1 focus:ring-max-primary"
                        >
                            <option
                                v-for="unit in WEIGHT_UNITS"
                                :key="unit.value"
                                :value="unit.value"
                            >
                                {{ unit.label }}
                            </option>
                        </select>
                    </div>
                </div>

                <div>
                    <label for="dish-price" class="mb-1 block text-sm font-medium text-gray-700">
                        Цена <span class="text-red-500">*</span>
                    </label>
                    <div class="relative">
                        <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-sm text-gray-500">
                            ₽
                        </span>
                        <input
                            id="dish-price"
                            v-model="price"
                            type="number"
                            min="0"
                            step="0.01"
                            class="w-full rounded-xl border border-gray-200 py-2.5 pl-8 pr-3 text-sm focus:border-max-primary focus:outline-none focus:ring-1 focus:ring-max-primary"
                            :class="displayFieldErrors.price ? 'border-red-300' : ''"
                        >
                    </div>
                    <p v-if="displayFieldErrors.price" class="mt-1 text-xs text-red-600">{{ displayFieldErrors.price }}</p>
                </div>

                <div>
                    <label for="dish-vat" class="mb-1 block text-sm font-medium text-gray-700">
                        НДС
                    </label>
                    <select
                        id="dish-vat"
                        v-model="vatRate"
                        class="w-full rounded-xl border border-gray-200 bg-white px-3 py-2.5 text-sm focus:border-max-primary focus:outline-none focus:ring-1 focus:ring-max-primary"
                    >
                        <option
                            v-for="option in VAT_OPTIONS"
                            :key="option.label"
                            :value="option.value"
                        >
                            {{ option.label }}
                        </option>
                    </select>
                </div>

                <label class="flex items-center gap-2 text-sm text-gray-700">
                    <input
                        v-model="isAvailable"
                        type="checkbox"
                        class="h-4 w-4 rounded border-gray-300 text-max-primary focus:ring-max-primary"
                    >
                    Блюдо доступно в меню
                </label>

                <div class="space-y-3 pt-1">
                    <button
                        type="submit"
                        class="w-full rounded-xl bg-max-primary py-3 text-sm font-semibold text-white transition hover:bg-max-primary/90 disabled:opacity-50"
                        :disabled="submitDisabled"
                    >
                        {{ submitLoading ? 'Сохранение…' : (isEditMode ? 'Сохранить изменения' : 'Создать блюдо') }}
                    </button>
                    <button
                        v-if="isEditMode"
                        type="button"
                        class="w-full rounded-xl border border-gray-200 px-4 py-3 text-sm font-medium text-gray-700 transition hover:bg-gray-50 disabled:opacity-50"
                        :disabled="submitDisabled"
                        @click="emit('back')"
                    >
                        Выйти без сохранения
                    </button>
                </div>
            </form>
        </main>
    </div>
</template>
