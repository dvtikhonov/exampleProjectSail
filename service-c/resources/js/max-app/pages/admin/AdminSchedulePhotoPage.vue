<script setup>
/**
 * Фото графика: форма → готовность PhotoText/sidecar → OCR → сводка matched + Issues → confirm apply.
 */
import { computed, onMounted, ref } from 'vue';
import AppSelect from '../../components/AppSelect.vue';
import { useSchedulePhoto } from '../../composables/useSchedulePhoto';

const {
    baseUrl,
    baseUrlSelectOptions,
    dateFrom,
    restaurantId,
    selectedCategoryIds,
    photoPreviewUrl,
    photoError,
    catalogLoading,
    catalogError,
    restaurantsLoading,
    categoriesLoading,
    categoriesError,
    readiness,
    readinessLoading,
    readinessError,
    readinessOk,
    readinessOkMessage,
    running,
    runProgress,
    runError,
    pollPaused,
    hasOpenOcrRun,
    scheduleApplying,
    applyError,
    applied,
    restaurantSelectOptions,
    categoryOptions,
    selectedRestaurantName,
    selectedCategoryNames,
    windowDateFrom,
    windowDateTo,
    matchedCount,
    matchedItems,
    issues,
    issuesNotFound,
    issuesAmbiguous,
    issuesOther,
    discarded,
    runCatalogError,
    catalogScopeSelectOptions,
    windowDates,
    datesOnPhoto,
    datesForResolve,
    isDiscardedDateSelected,
    toggleDiscardedResolveDate,
    formatDateChip,
    resolvingDiscarded,
    resolveDiscardedError,
    discardedResolveDishName,
    hasUnresolvedRows,
    hasMatchResult,
    isZeroMatchStop,
    isPartialMatch,
    canStart,
    canApply,
    formLocked,
    loadCatalog,
    reloadCategories,
    setBaseUrl,
    setDateFrom,
    setRestaurantId,
    setCategoryChecked,
    setAllCategoriesChecked,
    setPhotoFile,
    checkReadiness,
    startRun,
    stopOcrPoll,
    resumeOcrPoll,
    confirmApply,
    cancelMatch,
    resetForNewRun,
    setDiscardedResolveDishName,
    aliasHintLine,
    aliasMarkdownSnippet,
    resolveDiscardedRow,
    issueCodeLabel,
    discardedReasonLabel,
    formatDatesList,
} = useSchedulePhoto();

/** @type {import('vue').Ref<HTMLInputElement|null>} */
const photoInputRef = ref(null);

const allCategoriesSelected = computed(() => (
    categoryOptions.value.length > 0
    && selectedCategoryIds.value.length === categoryOptions.value.length
));

const matchedCountLabel = computed(() => {
    const n = matchedCount.value;
    const mod10 = n % 10;
    const mod100 = n % 100;

    if (mod10 === 1 && mod100 !== 11) {
        return 'позиция';
    }

    if (mod10 >= 2 && mod10 <= 4 && (mod100 < 12 || mod100 > 14)) {
        return 'позиции';
    }

    return 'позиций';
});

onMounted(() => {
    loadCatalog();
});

/**
 * @param {Event} event
 */
function onDateFromChange(event) {
    setDateFrom(/** @type {HTMLInputElement} */ (event.target).value);
}

/**
 * @param {number} categoryId
 * @param {Event} event
 */
function onCategoryCheckChange(categoryId, event) {
    setCategoryChecked(categoryId, /** @type {HTMLInputElement} */ (event.target).checked);
}

/**
 * @param {string} rawTitle
 * @param {string} date
 * @param {Event} event
 */
function onDiscardedDateCheckChange(rawTitle, date, event) {
    toggleDiscardedResolveDate(
        rawTitle,
        date,
        /** @type {HTMLInputElement} */ (event.target).checked,
    );
}

/**
 * @param {Event} event
 */
function onSelectAllCategories(event) {
    setAllCategoriesChecked(/** @type {HTMLInputElement} */ (event.target).checked);
}

/**
 * @param {Event} event
 */
function onPhotoChange(event) {
    const input = /** @type {HTMLInputElement} */ (event.target);
    const file = input.files?.[0] ?? null;
    setPhotoFile(file);

    if (photoError.value) {
        input.value = '';
    }
}

function onResetForNewRun() {
    resetForNewRun();

    if (photoInputRef.value) {
        photoInputRef.value.value = '';
    }
}
</script>

<template>
    <div class="flex min-h-0 flex-1 flex-col overflow-y-auto bg-gray-50">
        <div class="mx-auto w-full max-w-3xl space-y-4 p-4 pb-8">
            <header>
                <h1 class="text-lg font-semibold text-gray-900">
                    Фото графика
                </h1>
                <p class="mt-0.5 text-sm text-max-muted">
                    Загрузка фото → OCR → сверка с каталогом. Запись в график — только после подтверждения.
                </p>
            </header>

            <section
                class="rounded-2xl border border-gray-100 bg-white p-4 shadow-sm"
                aria-labelledby="schedule-photo-form-heading"
            >
                <div class="mb-3">
                    <h2
                        id="schedule-photo-form-heading"
                        class="text-sm font-semibold text-gray-900"
                    >
                        Параметры
                    </h2>
                    <p class="mt-0.5 text-xs text-max-muted">
                        Окно — 7 дней от даты начала. Apply заменяет даты в выбранных категориях.
                    </p>
                </div>

                <div
                    v-if="catalogError"
                    class="mb-3 rounded-xl border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700"
                >
                    {{ catalogError }}
                    <button
                        type="button"
                        class="mt-1 block text-sm font-medium text-red-800 underline"
                        @click="loadCatalog"
                    >
                        Повторить
                    </button>
                </div>

                <div
                    v-if="runError"
                    class="mb-3 rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-900"
                    role="alert"
                >
                    {{ runError }}
                </div>

                <div
                    v-if="runProgress"
                    class="mb-3 rounded-xl border border-sky-200 bg-sky-50 px-3 py-2 text-sm text-sky-800"
                    role="status"
                >
                    {{ runProgress }}
                </div>

                <div
                    v-if="pollPaused"
                    class="mb-3 rounded-xl border border-sky-200 bg-sky-50 px-3 py-2 text-sm text-sky-900"
                    role="status"
                >
                    Опрос остановлен. Прогон сохранён — распознавание на сервере может ещё идти.
                </div>

                <div class="space-y-3">
                    <div>
                        <label
                            class="mb-1.5 block text-sm font-medium text-gray-900"
                            for="schedule-photo-base-url"
                        >
                            BASE_URL
                        </label>
                        <AppSelect
                            id="schedule-photo-base-url"
                            :model-value="baseUrl"
                            :options="baseUrlSelectOptions"
                            :disabled="formLocked || catalogLoading || restaurantsLoading"
                            placeholder="Выберите origin"
                            @update:model-value="setBaseUrl"
                        />
                        <p class="mt-1 text-xs text-max-muted">
                            Только allow-list: локальный Docker или VPS (origin из MAX_MINI_APP_URL).
                        </p>
                    </div>

                    <div>
                        <label
                            class="mb-1.5 block text-sm font-medium text-gray-900"
                            for="schedule-photo-date-from"
                        >
                            Дата начала
                        </label>
                        <input
                            id="schedule-photo-date-from"
                            type="date"
                            class="w-full rounded-xl border border-gray-200 bg-white px-3 py-2.5 text-sm text-gray-900 outline-none focus:border-max-primary focus:ring-2 focus:ring-max-primary/20 disabled:bg-gray-50"
                            :value="dateFrom"
                            :disabled="formLocked"
                            @change="onDateFromChange"
                        >
                        <p class="mt-1 text-xs text-max-muted">
                            Диапазон: {{ windowDateFrom }} — {{ windowDateTo }}
                        </p>
                    </div>

                    <div>
                        <label
                            class="mb-1.5 block text-sm font-medium text-gray-900"
                            for="schedule-photo-restaurant"
                        >
                            Ресторан
                        </label>
                        <AppSelect
                            id="schedule-photo-restaurant"
                            :model-value="restaurantId"
                            :options="restaurantSelectOptions"
                            :disabled="catalogLoading || restaurantsLoading || formLocked"
                            placeholder="Выберите ресторан"
                            @update:model-value="setRestaurantId"
                        />
                        <p
                            v-if="restaurantsLoading"
                            class="mt-1 text-xs text-max-muted"
                        >
                            Загрузка ресторанов…
                        </p>
                        <p
                            v-else-if="baseUrl && !catalogLoading && !catalogError && restaurantSelectOptions.length <= 1"
                            class="mt-1 text-xs text-max-muted"
                        >
                            Нет ресторанов на выбранном origin
                        </p>
                    </div>

                    <div>
                        <div class="mb-1.5 flex items-center justify-between gap-2">
                            <span class="text-sm font-medium text-gray-900">
                                Категории
                            </span>
                            <label
                                v-if="categoryOptions.length > 0"
                                class="flex cursor-pointer items-center gap-1.5 text-xs text-max-muted"
                            >
                                <input
                                    type="checkbox"
                                    class="rounded border-gray-300 text-max-primary focus:ring-max-primary/30"
                                    :checked="allCategoriesSelected"
                                    :disabled="formLocked"
                                    @change="onSelectAllCategories"
                                >
                                Все
                            </label>
                        </div>

                        <p
                            v-if="!restaurantId"
                            class="rounded-xl border border-dashed border-gray-200 bg-gray-50 px-3 py-2.5 text-sm text-max-muted"
                        >
                            Сначала выберите ресторан
                        </p>
                        <p
                            v-else-if="categoriesLoading"
                            class="rounded-xl border border-dashed border-gray-200 bg-gray-50 px-3 py-2.5 text-sm text-max-muted"
                        >
                            Загрузка категорий…
                        </p>
                        <div
                            v-else-if="categoriesError"
                            class="rounded-xl border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700"
                        >
                            {{ categoriesError }}
                            <button
                                type="button"
                                class="mt-1 block text-sm font-medium text-red-800 underline"
                                @click="reloadCategories"
                            >
                                Повторить
                            </button>
                        </div>
                        <p
                            v-else-if="categoryOptions.length === 0"
                            class="rounded-xl border border-dashed border-gray-200 bg-gray-50 px-3 py-2.5 text-sm text-max-muted"
                        >
                            Нет категорий для ресторана
                        </p>
                        <ul
                            v-else
                            class="max-h-48 space-y-1.5 overflow-y-auto rounded-xl border border-gray-200 p-2"
                        >
                            <li
                                v-for="category in categoryOptions"
                                :key="category.id"
                            >
                                <label class="flex cursor-pointer items-center gap-2 rounded-lg px-2 py-1.5 text-sm text-gray-900 hover:bg-gray-50">
                                    <input
                                        type="checkbox"
                                        class="rounded border-gray-300 text-max-primary focus:ring-max-primary/30"
                                        :checked="selectedCategoryIds.includes(category.id)"
                                        :disabled="formLocked"
                                        @change="onCategoryCheckChange(category.id, $event)"
                                    >
                                    {{ category.name }}
                                </label>
                            </li>
                        </ul>
                    </div>

                    <div>
                        <label
                            class="mb-1.5 block text-sm font-medium text-gray-900"
                            for="schedule-photo-file"
                        >
                            Фото графика
                        </label>
                        <input
                            id="schedule-photo-file"
                            ref="photoInputRef"
                            type="file"
                            accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
                            class="block w-full text-sm text-gray-700 file:mr-3 file:rounded-lg file:border-0 file:bg-max-primary/10 file:px-3 file:py-2 file:text-sm file:font-medium file:text-max-primary disabled:opacity-60"
                            :disabled="formLocked"
                            :aria-invalid="photoError ? 'true' : undefined"
                            :aria-describedby="photoError ? 'schedule-photo-file-error' : undefined"
                            @change="onPhotoChange"
                        >
                        <p
                            v-if="photoError"
                            id="schedule-photo-file-error"
                            class="mt-1 text-xs text-red-600"
                            role="alert"
                        >
                            {{ photoError }}
                        </p>
                        <div
                            v-if="photoPreviewUrl"
                            class="mt-3 overflow-hidden rounded-xl border border-gray-200 bg-gray-50"
                        >
                            <img
                                :src="photoPreviewUrl"
                                alt="Превью фото графика"
                                class="mx-auto max-h-64 w-auto object-contain"
                            >
                        </div>
                    </div>
                </div>

                <div
                    v-if="readinessError"
                    class="mt-3 rounded-xl border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700"
                    role="alert"
                >
                    {{ readinessError }}
                </div>

                <div
                    v-if="readiness && readinessOk"
                    class="mt-3 rounded-xl border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-800"
                >
                    {{ readinessOkMessage }}
                </div>

                <ul
                    v-if="readiness && !readinessOk"
                    class="mt-3 space-y-1 text-xs text-max-muted"
                >
                    <li :class="readiness.ai_access.ok ? 'text-emerald-700' : 'text-red-700'">
                        AI: {{ readiness.ai_access.message }}
                    </li>
                    <li :class="readiness.phototext.ok ? 'text-emerald-700' : 'text-red-700'">
                        PhotoText: {{ readiness.phototext.message }}
                    </li>
                    <li :class="readiness.sidecar.ok ? 'text-emerald-700' : 'text-red-700'">
                        Sidecar: {{ readiness.sidecar.message }}
                    </li>
                </ul>

                <div class="mt-4 flex flex-wrap gap-2">
                    <button
                        type="button"
                        class="rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm font-medium text-gray-900 hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50"
                        :disabled="readinessLoading || formLocked || !baseUrl.trim()"
                        @click="checkReadiness"
                    >
                        {{ readinessLoading ? 'Проверка…' : 'Проверить готовность' }}
                    </button>
                    <button
                        v-if="!hasOpenOcrRun"
                        type="button"
                        class="rounded-xl bg-max-primary px-4 py-2.5 text-sm font-medium text-white hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-50"
                        :disabled="!canStart"
                        @click="startRun"
                    >
                        {{ running ? 'Запуск…' : 'Запустить' }}
                    </button>
                    <button
                        v-if="running"
                        type="button"
                        class="rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm font-medium text-gray-900 hover:bg-gray-50"
                        @click="stopOcrPoll"
                    >
                        Остановить
                    </button>
                    <button
                        v-if="hasOpenOcrRun && !running"
                        type="button"
                        class="rounded-xl bg-max-primary px-4 py-2.5 text-sm font-medium text-white hover:opacity-90"
                        @click="resumeOcrPoll"
                    >
                        Продолжить опрос
                    </button>
                    <button
                        v-if="hasOpenOcrRun && !running"
                        type="button"
                        class="rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm font-medium text-gray-900 hover:bg-gray-50"
                        @click="cancelMatch"
                    >
                        Отменить прогон
                    </button>
                    <button
                        v-if="applied"
                        type="button"
                        class="rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm font-medium text-gray-900 hover:bg-gray-50"
                        @click="onResetForNewRun"
                    >
                        Новый прогон
                    </button>
                </div>
            </section>

            <section
                v-if="hasMatchResult"
                class="rounded-2xl border border-gray-100 bg-white p-4 shadow-sm"
                aria-labelledby="schedule-photo-matched-heading"
            >
                <div class="mb-3">
                    <h2
                        id="schedule-photo-matched-heading"
                        class="text-sm font-semibold text-gray-900"
                    >
                        Сводка matched
                    </h2>
                    <p class="mt-0.5 text-xs text-max-muted">
                        Apply заменяет даты в окне в указанных категориях.
                    </p>
                </div>

                <dl class="mb-3 grid gap-2 text-sm text-gray-800 sm:grid-cols-2">
                    <div>
                        <dt class="text-xs text-max-muted">
                            Ресторан
                        </dt>
                        <dd>
                            {{ selectedRestaurantName || '—' }}
                            <span
                                v-if="restaurantId"
                                class="text-max-muted"
                            >(id {{ restaurantId }})</span>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs text-max-muted">
                            Диапазон
                        </dt>
                        <dd>
                            {{ windowDateFrom }} — {{ windowDateTo }}
                        </dd>
                    </div>
                    <div class="sm:col-span-2">
                        <dt class="text-xs text-max-muted">
                            Категории
                        </dt>
                        <dd>
                            <span
                                v-for="(cat, index) in selectedCategoryNames"
                                :key="cat.id"
                            >
                                {{ cat.name }} (id {{ cat.id }})<template v-if="index < selectedCategoryNames.length - 1">, </template>
                            </span>
                            <span v-if="selectedCategoryNames.length === 0">—</span>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs text-max-muted">
                            Matched
                        </dt>
                        <dd class="font-medium">
                            {{ matchedCount }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs text-max-muted">
                            Issues / отброшено
                        </dt>
                        <dd>
                            {{ issues.length }} / {{ discarded.length }}
                        </dd>
                    </div>
                </dl>

                <div
                    v-if="matchedCount === 0"
                    class="rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-900"
                    role="alert"
                >
                    Нет matched позиций — запись графика недоступна. Исправьте каталог или фото и перезапустите.
                </div>

                <ul
                    v-else
                    class="divide-y divide-gray-100 rounded-xl border border-gray-100"
                >
                    <li
                        v-for="item in matchedItems"
                        :key="`${item.dish_id}-${item.raw_title}`"
                        class="px-3 py-2.5 text-sm"
                    >
                        <div class="font-medium text-gray-900">
                            {{ item.dish_name }}
                        </div>
                        <div class="mt-0.5 text-xs text-max-muted">
                            {{ item.category_name }}
                            <template v-if="item.raw_title && item.raw_title !== item.dish_name">
                                · с фото: «{{ item.raw_title }}»
                            </template>
                        </div>
                        <div class="mt-1 text-xs text-gray-700">
                            Даты: {{ formatDatesList(item.dates) }}
                        </div>
                    </li>
                </ul>
            </section>

            <section
                v-if="hasMatchResult"
                class="rounded-2xl border border-gray-100 bg-white p-4 shadow-sm"
                aria-labelledby="schedule-photo-issues-heading"
            >
                <div class="mb-3">
                    <h2
                        id="schedule-photo-issues-heading"
                        class="text-sm font-semibold text-gray-900"
                    >
                        Issues
                        <span
                            v-if="hasUnresolvedRows"
                            class="ml-1 font-normal text-max-muted"
                        >
                            (matcher {{ issues.length }} · отброшено {{ discarded.length }})
                        </span>
                    </h2>
                    <p class="mt-0.5 text-xs text-max-muted">
                        Не редактируются в v1 и не попадают в apply. Резолв — поправить каталог/фото и перезапустить.
                    </p>
                </div>

                <p
                    v-if="!hasUnresolvedRows"
                    class="rounded-xl border border-dashed border-gray-200 bg-gray-50 px-3 py-2.5 text-sm text-max-muted"
                >
                    Проблемных строк нет.
                </p>

                <div
                    v-if="issuesNotFound.length > 0"
                    class="mb-4"
                >
                    <h3 class="mb-2 text-xs font-semibold uppercase tracking-wide text-max-muted">
                        Не найдено в каталоге ({{ issuesNotFound.length }})
                    </h3>
                    <ul class="divide-y divide-gray-100 rounded-xl border border-rose-100 bg-rose-50/30">
                        <li
                            v-for="issue in issuesNotFound"
                            :key="`not-found-${issue.raw_title}-${issue.code}-${(issue.dates || []).join(',')}`"
                            class="px-3 py-2.5 text-sm"
                        >
                            <div class="flex flex-wrap items-baseline gap-x-2 gap-y-0.5">
                                <span class="font-medium text-gray-900">
                                    «{{ issue.raw_title }}»
                                </span>
                                <span class="rounded bg-rose-100 px-1.5 py-0.5 text-xs text-rose-800">
                                    {{ issueCodeLabel(issue.code) }}
                                </span>
                            </div>
                            <p class="mt-0.5 text-xs text-max-muted">
                                {{ issue.message }}
                            </p>
                            <p
                                v-if="issue.dates?.length"
                                class="mt-1 text-xs text-gray-700"
                            >
                                Даты: {{ formatDatesList(issue.dates) }}
                            </p>
                            <p class="mt-1 text-xs text-rose-700/80">
                                Не в apply
                            </p>
                        </li>
                    </ul>
                </div>

                <div
                    v-if="issuesAmbiguous.length > 0"
                    class="mb-4"
                >
                    <h3 class="mb-2 text-xs font-semibold uppercase tracking-wide text-max-muted">
                        Неоднозначно — не угадываем ({{ issuesAmbiguous.length }})
                    </h3>
                    <ul class="divide-y divide-gray-100 rounded-xl border border-amber-100 bg-amber-50/30">
                        <li
                            v-for="issue in issuesAmbiguous"
                            :key="`ambiguous-${issue.raw_title}-${issue.code}-${(issue.dates || []).join(',')}`"
                            class="px-3 py-2.5 text-sm"
                        >
                            <div class="flex flex-wrap items-baseline gap-x-2 gap-y-0.5">
                                <span class="font-medium text-gray-900">
                                    «{{ issue.raw_title }}»
                                </span>
                                <span class="rounded bg-amber-100 px-1.5 py-0.5 text-xs text-amber-900">
                                    {{ issueCodeLabel(issue.code) }}
                                </span>
                            </div>
                            <p class="mt-0.5 text-xs text-max-muted">
                                {{ issue.message }}
                            </p>
                            <p
                                v-if="issue.dates?.length"
                                class="mt-1 text-xs text-gray-700"
                            >
                                Даты: {{ formatDatesList(issue.dates) }}
                            </p>
                            <p class="mt-1 text-xs text-amber-800/80">
                                Не в apply
                            </p>
                        </li>
                    </ul>
                </div>

                <div
                    v-if="issuesOther.length > 0"
                    class="mb-4"
                >
                    <h3 class="mb-2 text-xs font-semibold uppercase tracking-wide text-max-muted">
                        Прочие issues ({{ issuesOther.length }})
                    </h3>
                    <ul class="divide-y divide-gray-100 rounded-xl border border-gray-100">
                        <li
                            v-for="issue in issuesOther"
                            :key="`other-${issue.raw_title}-${issue.code}-${(issue.dates || []).join(',')}`"
                            class="px-3 py-2.5 text-sm"
                        >
                            <div class="flex flex-wrap items-baseline gap-x-2 gap-y-0.5">
                                <span class="font-medium text-gray-900">
                                    «{{ issue.raw_title }}»
                                </span>
                                <span class="rounded bg-gray-100 px-1.5 py-0.5 text-xs text-gray-700">
                                    {{ issueCodeLabel(issue.code) }}
                                </span>
                            </div>
                            <p class="mt-0.5 text-xs text-max-muted">
                                {{ issue.message }}
                            </p>
                            <p
                                v-if="issue.dates?.length"
                                class="mt-1 text-xs text-gray-700"
                            >
                                Даты: {{ formatDatesList(issue.dates) }}
                            </p>
                        </li>
                    </ul>
                </div>

                <div v-if="discarded.length > 0">
                    <h3 class="mb-2 text-xs font-semibold uppercase tracking-wide text-max-muted">
                        Не отправлено в match ({{ discarded.length }})
                    </h3>
                    <p class="mb-2 text-xs text-max-muted">
                        OCR/агент не включил строку в entries (0 или &gt;1 кандидат до POST).
                        <template v-if="!runCatalogError">
                            Можно выбрать канон по алиасу или вручную из каталога.
                            При ручном выборе новая пара сохранится в phototext-dish-aliases.md.
                        </template>
                        В график попадают только дни с фото, не всё окно прогона.
                    </p>
                    <p
                        v-if="runCatalogError"
                        class="mb-2 text-xs text-red-700"
                        role="alert"
                    >
                        {{ runCatalogError }}
                    </p>
                    <p
                        v-if="resolveDiscardedError"
                        class="mb-2 text-xs text-red-700"
                        role="alert"
                    >
                        {{ resolveDiscardedError }}
                    </p>
                    <ul class="divide-y divide-gray-100 rounded-xl border border-slate-100 bg-slate-50/40">
                        <li
                            v-for="row in discarded"
                            :key="`discarded-${row.raw_title}-${row.reason}-${(row.dates || []).join(',')}`"
                            class="px-3 py-2.5 text-sm"
                        >
                            <div class="flex flex-wrap items-baseline gap-x-2 gap-y-0.5">
                                <span class="font-medium text-gray-900">
                                    «{{ row.raw_title }}»
                                </span>
                                <span class="rounded bg-slate-200/80 px-1.5 py-0.5 text-xs text-slate-800">
                                    {{ discardedReasonLabel(row.reason) }}
                                </span>
                            </div>
                            <p
                                v-if="row.reason
                                    && row.reason !== 'dish_not_found'
                                    && row.reason !== 'dish_ambiguous'
                                    && row.reason !== 'out_of_window'
                                    && row.reason !== 'unspecified'"
                                class="mt-0.5 text-xs text-max-muted"
                            >
                                reason: {{ row.reason }}
                            </p>
                            <p
                                v-if="aliasHintLine(row)"
                                class="mt-1.5 text-xs text-slate-700"
                            >
                                {{ aliasHintLine(row) }}
                            </p>
                            <p
                                v-if="datesOnPhoto(row).length > 0"
                                class="mt-1 text-xs text-gray-700"
                            >
                                День с фото: {{ formatDatesList(datesOnPhoto(row)) }}
                            </p>

                            <div
                                v-if="!applied && hasMatchResult && !runCatalogError"
                                class="mt-2 space-y-2"
                            >
                                <div v-if="datesOnPhoto(row).length === 0">
                                    <p class="mb-1.5 text-xs text-max-muted">
                                        На фото день — заголовок группы. Отметьте только его, не всю неделю.
                                    </p>
                                    <ul class="flex flex-wrap gap-1.5">
                                        <li
                                            v-for="date in windowDates"
                                            :key="`${row.raw_title}-${date}`"
                                        >
                                            <label class="flex cursor-pointer items-center gap-1.5 rounded-lg border border-gray-200 bg-white px-2 py-1 text-xs text-gray-900">
                                                <input
                                                    type="checkbox"
                                                    class="rounded border-gray-300 text-max-primary focus:ring-max-primary/30"
                                                    :checked="isDiscardedDateSelected(row.raw_title, date)"
                                                    :disabled="resolvingDiscarded || applied"
                                                    @change="onDiscardedDateCheckChange(row.raw_title, date, $event)"
                                                >
                                                {{ formatDateChip(date) }}
                                            </label>
                                        </li>
                                    </ul>
                                </div>
                                <div
                                    v-if="!row.suggested_in_scope"
                                    class="max-w-md"
                                >
                                    <AppSelect
                                        :model-value="discardedResolveDishName[row.raw_title] || ''"
                                        :options="catalogScopeSelectOptions"
                                        placeholder="Блюдо из каталога"
                                        size="sm"
                                        :disabled="resolvingDiscarded || applied"
                                        @update:model-value="setDiscardedResolveDishName(row.raw_title, $event)"
                                    />
                                    <p
                                        v-if="row.alias_missing && discardedResolveDishName[row.raw_title]"
                                        class="mt-1 font-mono text-[11px] text-max-muted"
                                    >
                                        В словарь:
                                        {{ aliasMarkdownSnippet(row) }}
                                    </p>
                                </div>

                                <button
                                    type="button"
                                    class="rounded-lg bg-slate-800 px-3 py-1.5 text-xs font-medium text-white disabled:opacity-50"
                                    :disabled="resolvingDiscarded || applied
                                        || datesForResolve(row).length === 0
                                        || (!row.suggested_in_scope && !(discardedResolveDishName[row.raw_title] || '').trim())"
                                    @click="resolveDiscardedRow(row)"
                                >
                                    <template v-if="row.suggested_in_scope">
                                        Выбрать по алиасу
                                    </template>
                                    <template v-else>
                                        Выбрать и сохранить алиас
                                    </template>
                                </button>
                            </div>
                        </li>
                    </ul>
                </div>
            </section>

            <section
                v-if="hasMatchResult && !applied"
                class="rounded-2xl border border-gray-100 bg-white p-4 shadow-sm"
                aria-labelledby="schedule-photo-confirm-heading"
            >
                <h2
                    id="schedule-photo-confirm-heading"
                    class="text-sm font-semibold text-gray-900"
                >
                    {{ isZeroMatchStop ? 'Запись недоступна' : 'Записать график?' }}
                </h2>

                <div
                    v-if="isZeroMatchStop"
                    class="mt-2 rounded-xl border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800"
                    role="alert"
                >
                    STOP: matched_count = 0
                    <template v-if="hasUnresolvedRows">
                        (все строки в issues / отброшенных).
                    </template>
                    <template v-else>
                        (пустой match).
                    </template>
                    Кнопка «Записать» недоступна — исправьте каталог или фото и перезапустите.
                </div>

                <div
                    v-else-if="isPartialMatch"
                    class="mt-2 rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-900"
                    role="status"
                >
                    Частичный матч: в график попадут только matched ({{ matchedCount }}).
                    Issues ({{ issues.length }}) и отброшенные ({{ discarded.length }}) останутся вне БД.
                    Очистка выбранных категорий в окне 7 дней всё равно полная.
                </div>

                <div
                    v-if="applyError"
                    class="mt-2 rounded-xl border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700"
                    role="alert"
                >
                    {{ applyError }}
                </div>

                <div class="mt-3 flex flex-wrap gap-2">
                    <button
                        v-if="!isZeroMatchStop"
                        type="button"
                        class="rounded-xl bg-max-primary px-4 py-2.5 text-sm font-medium text-white hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-50"
                        :disabled="!canApply"
                        @click="confirmApply"
                    >
                        {{ scheduleApplying ? 'Запись…' : 'Записать график' }}
                    </button>
                    <button
                        type="button"
                        class="rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm font-medium text-gray-900 hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50"
                        :disabled="scheduleApplying || running"
                        @click="cancelMatch"
                    >
                        {{ isZeroMatchStop ? 'Закрыть результат' : 'Отмена' }}
                    </button>
                </div>
            </section>

            <section
                v-if="applied && hasMatchResult"
                class="rounded-2xl border border-emerald-100 bg-white p-4 shadow-sm"
                aria-labelledby="schedule-photo-applied-heading"
            >
                <h2
                    id="schedule-photo-applied-heading"
                    class="text-sm font-semibold text-emerald-900"
                >
                    График записан
                </h2>
                <p class="mt-1 text-sm text-gray-700">
                    Applied: {{ matchedCount }} {{ matchedCountLabel }}
                    за {{ windowDateFrom }} — {{ windowDateTo }}.
                </p>

                <ul
                    v-if="matchedItems.length > 0"
                    class="mt-3 divide-y divide-gray-100 rounded-xl border border-gray-100"
                >
                    <li
                        v-for="item in matchedItems"
                        :key="`applied-${item.dish_id}`"
                        class="px-3 py-2 text-sm"
                    >
                        <span class="font-medium text-gray-900">{{ item.dish_name }}</span>
                        <span class="text-max-muted"> — {{ formatDatesList(item.dates) }}</span>
                    </li>
                </ul>

                <div
                    v-if="hasUnresolvedRows"
                    class="mt-3 rounded-xl border border-amber-100 bg-amber-50/50 px-3 py-2 text-xs text-amber-900"
                >
                    Оставшиеся вне БД: issues {{ issues.length }}
                    (не найдено {{ issuesNotFound.length }}, неоднозначно {{ issuesAmbiguous.length }})
                    · отброшенные {{ discarded.length }}. См. блок Issues выше.
                </div>
            </section>
        </div>
    </div>
</template>
