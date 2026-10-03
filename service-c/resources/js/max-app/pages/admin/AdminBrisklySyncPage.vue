<script setup>
/**
 * Синхронизация Briskly: поиск → результаты → «Изменить цены» / «Создать в Briskly».
 * Категория Briskly только на строках CREATE; equal не пишем.
 */
import { onMounted } from 'vue';
import AppSelect from '../../components/AppSelect.vue';
import { useBrisklySync } from '../../composables/useBrisklySync';

const {
    restaurantsLoading,
    restaurantsError,
    vpsCategoriesLoading,
    vpsCategoriesError,
    restaurantId,
    brisklyToken,
    vpsCategoryId,
    searchText,
    clarification,
    searching,
    searchError,
    searchProgress,
    sessionStatus,
    priceUpdateItems,
    priceUpdateTotal,
    priceUpdateShown,
    priceUpdateTruncationMessage,
    createItems,
    createTotal,
    createShown,
    createTruncationMessage,
    skippedBrisklyOnly,
    ambiguousCount,
    equalPriceCount,
    priceUpdateChecked,
    createChecked,
    createCategoryByLine,
    brisklyCategoriesLoading,
    brisklyCategoriesError,
    applying,
    applyError,
    applyReport,
    sessionApplied,
    hasResults,
    resultsLocked,
    restaurantSelectOptions,
    vpsCategorySelectOptions,
    brisklyCategorySelectOptions,
    canSearch,
    canApplyPriceUpdates,
    canApplyCreates,
    checkedPriceUpdateCount,
    checkedCreateCount,
    loadRestaurants,
    setRestaurantId,
    setBrisklyToken,
    setVpsCategoryId,
    setSearchText,
    setClarification,
    setPriceUpdateChecked,
    setCreateChecked,
    setCreateCategory,
    isCreateCategoryMissing,
    isLargePriceDelta,
    runSearch,
    applyPriceUpdates,
    applyCreates,
} = useBrisklySync();

onMounted(() => {
    loadRestaurants();
});

/**
 * @param {Event} event
 */
function onTokenInput(event) {
    setBrisklyToken(/** @type {HTMLInputElement} */ (event.target).value);
}

/**
 * @param {Event} event
 */
function onSearchTextInput(event) {
    setSearchText(/** @type {HTMLInputElement} */ (event.target).value);
}

/**
 * @param {Event} event
 */
function onClarificationInput(event) {
    setClarification(/** @type {HTMLTextAreaElement} */ (event.target).value);
}

/**
 * @param {string} lineKey
 * @param {Event} event
 */
function onPriceCheckChange(lineKey, event) {
    setPriceUpdateChecked(lineKey, /** @type {HTMLInputElement} */ (event.target).checked);
}

/**
 * @param {string} lineKey
 * @param {Event} event
 */
function onCreateCheckChange(lineKey, event) {
    setCreateChecked(lineKey, /** @type {HTMLInputElement} */ (event.target).checked);
}

/**
 * @param {string} price
 * @returns {string}
 */
function formatPrice(price) {
    const numeric = Number(price);

    if (!Number.isFinite(numeric)) {
        return String(price ?? '');
    }

    return numeric.toLocaleString('ru-RU', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
}
</script>

<template>
    <div class="flex min-h-0 flex-1 flex-col overflow-y-auto bg-gray-50">
        <div class="mx-auto w-full max-w-3xl space-y-4 p-4 pb-8">
            <header>
                <h1 class="text-lg font-semibold text-gray-900">
                    Синхронизация Briskly
                </h1>
                <p class="mt-0.5 text-sm text-max-muted">
                    Поиск расхождений цен и позиций для создания в Briskly. Запись — только после явного одобрения.
                </p>
            </header>

            <section
                class="rounded-2xl border border-gray-100 bg-white p-4 shadow-sm"
                aria-labelledby="briskly-sync-search-heading"
            >
                <div class="mb-3">
                    <h2
                        id="briskly-sync-search-heading"
                        class="text-sm font-semibold text-gray-900"
                    >
                        Поиск
                    </h2>
                    <p class="mt-0.5 text-xs text-max-muted">
                        Ресторан, токен Briskly, категория VPS и текст. Категория Briskly в фильтре не используется.
                    </p>
                </div>

                <div
                    v-if="restaurantsError"
                    class="mb-3 rounded-xl border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700"
                >
                    {{ restaurantsError }}
                    <button
                        type="button"
                        class="mt-1 block text-sm font-medium text-red-800 underline"
                        @click="loadRestaurants"
                    >
                        Повторить
                    </button>
                </div>

                <div
                    v-if="searchError"
                    class="mb-3 rounded-xl border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700"
                >
                    {{ searchError }}
                </div>

                <div
                    v-if="searchProgress"
                    class="mb-3 rounded-xl border border-sky-200 bg-sky-50 px-3 py-2 text-sm text-sky-800"
                >
                    {{ searchProgress }}
                </div>

                <div class="space-y-3">
                    <div>
                        <label
                            class="mb-1.5 block text-sm font-medium text-gray-900"
                            for="briskly-sync-restaurant"
                        >
                            Ресторан
                        </label>
                        <AppSelect
                            id="briskly-sync-restaurant"
                            :model-value="restaurantId"
                            :options="restaurantSelectOptions"
                            :disabled="restaurantsLoading || searching || applying"
                            placeholder="Выберите ресторан"
                            @update:model-value="setRestaurantId"
                        />
                    </div>

                    <div>
                        <label
                            class="mb-1.5 block text-sm font-medium text-gray-900"
                            for="briskly-sync-token"
                        >
                            Bearer Briskly
                        </label>
                        <input
                            id="briskly-sync-token"
                            type="password"
                            autocomplete="off"
                            class="w-full rounded-xl border border-gray-200 bg-white px-3 py-2.5 text-sm text-gray-900 outline-none focus:border-max-primary focus:ring-2 focus:ring-max-primary/20 disabled:bg-gray-50"
                            :value="brisklyToken"
                            :disabled="searching || applying"
                            placeholder="Токен только на сессию"
                            @input="onTokenInput"
                        >
                        <p class="mt-1 text-xs text-max-muted">
                            JWT из DevTools (Authorization). Префикс «Bearer » можно не убирать — снимем сами. Не сохраняется в браузере.
                        </p>
                    </div>

                    <div>
                        <label
                            class="mb-1.5 block text-sm font-medium text-gray-900"
                            for="briskly-sync-vps-category"
                        >
                            Категория VPS
                        </label>
                        <AppSelect
                            id="briskly-sync-vps-category"
                            :model-value="vpsCategoryId"
                            :options="vpsCategorySelectOptions"
                            :disabled="!restaurantId || vpsCategoriesLoading || searching || applying"
                            placeholder="Все категории"
                            @update:model-value="setVpsCategoryId"
                        />
                        <p
                            v-if="vpsCategoriesError"
                            class="mt-1 text-xs text-rose-600"
                        >
                            {{ vpsCategoriesError }}
                        </p>
                    </div>

                    <div>
                        <label
                            class="mb-1.5 block text-sm font-medium text-gray-900"
                            for="briskly-sync-search-text"
                        >
                            Текст поиска
                        </label>
                        <input
                            id="briskly-sync-search-text"
                            type="search"
                            maxlength="120"
                            class="w-full rounded-xl border border-gray-200 bg-white px-3 py-2.5 text-sm text-gray-900 outline-none focus:border-max-primary focus:ring-2 focus:ring-max-primary/20 disabled:bg-gray-50"
                            :value="searchText"
                            :disabled="searching || applying"
                            placeholder="Подстрока в VPS и Briskly"
                            @input="onSearchTextInput"
                        >
                    </div>

                    <div>
                        <label
                            class="mb-1.5 block text-sm font-medium text-gray-900"
                            for="briskly-sync-clarification"
                        >
                            Уточнение
                        </label>
                        <textarea
                            id="briskly-sync-clarification"
                            rows="3"
                            maxlength="2000"
                            class="w-full resize-y rounded-xl border border-gray-200 bg-white px-3 py-2.5 text-sm text-gray-900 outline-none focus:border-max-primary focus:ring-2 focus:ring-max-primary/20 disabled:bg-gray-50"
                            :value="clarification"
                            :disabled="searching || applying"
                            placeholder="Исключение или нормализация имён (напр. «только без шубы»)"
                            @input="onClarificationInput"
                        />
                    </div>

                    <button
                        type="button"
                        class="inline-flex w-full items-center justify-center rounded-xl bg-max-primary px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-max-primary/90 disabled:cursor-not-allowed disabled:opacity-60 sm:w-auto"
                        :disabled="!canSearch"
                        @click="runSearch"
                    >
                        {{ searching ? 'Ищем…' : 'Найти' }}
                    </button>
                </div>
            </section>

            <section
                v-if="hasResults"
                class="space-y-4"
                aria-labelledby="briskly-sync-results-heading"
            >
                <div class="flex flex-wrap items-end justify-between gap-2">
                    <div>
                        <h2
                            id="briskly-sync-results-heading"
                            class="text-sm font-semibold text-gray-900"
                        >
                            Результаты
                        </h2>
                        <p
                            v-if="sessionStatus"
                            class="mt-0.5 text-xs text-max-muted"
                        >
                            Статус сессии: {{ sessionStatus }}
                        </p>
                    </div>
                    <p class="text-xs text-max-muted">
                        Равные цены: {{ equalPriceCount }} · Неоднозначные: {{ ambiguousCount }} · Только Briskly: {{ skippedBrisklyOnly }}
                    </p>
                </div>

                <div
                    v-if="applyError"
                    class="rounded-xl border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700"
                    role="alert"
                >
                    {{ applyError }}
                </div>

                <div
                    v-if="applyReport"
                    class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4 shadow-sm"
                    role="status"
                    aria-labelledby="briskly-sync-report-heading"
                >
                    <h3
                        id="briskly-sync-report-heading"
                        class="text-sm font-semibold text-emerald-900"
                    >
                        Отчёт записи в Briskly
                    </h3>
                    <ul class="mt-2 space-y-1 text-sm text-emerald-900">
                        <li>Обновлено цен: {{ applyReport.updated }}</li>
                        <li>Создано позиций: {{ applyReport.created }}</li>
                        <li>Пропущено (не отмечено): {{ applyReport.skipped_unchecked }}</li>
                        <li>Пропущено (равные цены): {{ applyReport.skipped_equal }}</li>
                    </ul>
                    <ul
                        v-if="applyReport.errors.length > 0"
                        class="mt-3 space-y-1 border-t border-emerald-200 pt-3 text-sm text-rose-700"
                    >
                        <li
                            v-for="(error, index) in applyReport.errors"
                            :key="`${error.line_key}-${index}`"
                        >
                            <span v-if="error.line_key">{{ error.line_key }}: </span>{{ error.message }}
                        </li>
                    </ul>
                    <p
                        v-if="sessionApplied"
                        class="mt-3 text-xs text-emerald-800"
                    >
                        Сессия применена. Для повторной записи выполните новый поиск.
                    </p>
                </div>

                <!-- Секция A: UPDATE -->
                <div class="rounded-2xl border border-gray-100 bg-white p-4 shadow-sm">
                    <div class="mb-3 flex flex-wrap items-baseline justify-between gap-2">
                        <h3 class="text-sm font-semibold text-gray-900">
                            Расхождение цен
                        </h3>
                        <span class="text-xs text-max-muted">
                            Показано {{ priceUpdateShown }} из {{ priceUpdateTotal }}
                        </span>
                    </div>

                    <div
                        v-if="priceUpdateTruncationMessage"
                        class="mb-3 rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-900"
                        role="status"
                    >
                        {{ priceUpdateTruncationMessage }}
                    </div>

                    <p
                        v-if="priceUpdateItems.length === 0"
                        class="text-sm text-max-muted"
                    >
                        Нет позиций с разной ценой.
                    </p>

                    <ul
                        v-else
                        class="divide-y divide-gray-100"
                    >
                        <li
                            v-for="item in priceUpdateItems"
                            :key="item.line_key"
                            class="flex gap-3 py-3 first:pt-0 last:pb-0"
                        >
                            <input
                                :id="`briskly-price-${item.line_key}`"
                                type="checkbox"
                                class="mt-1 h-4 w-4 shrink-0 rounded border-gray-300 text-max-primary focus:ring-max-primary/30 disabled:opacity-60"
                                :checked="priceUpdateChecked[item.line_key] === true"
                                :disabled="resultsLocked"
                                @change="onPriceCheckChange(item.line_key, $event)"
                            >
                            <div class="min-w-0 flex-1">
                                <label
                                    class="block text-sm font-medium text-gray-900"
                                    :for="`briskly-price-${item.line_key}`"
                                >
                                    {{ item.display_name }}
                                </label>
                                <p
                                    v-if="item.briskly_display_name && item.briskly_display_name !== item.display_name"
                                    class="mt-0.5 truncate text-xs text-max-muted"
                                >
                                    Briskly: {{ item.briskly_display_name }}
                                </p>
                                <div class="mt-1.5 flex flex-wrap items-center gap-3 text-xs sm:text-sm">
                                    <span class="text-gray-700">
                                        VPS:
                                        <strong class="font-semibold">{{ formatPrice(item.source_price) }}</strong>
                                    </span>
                                    <span class="text-gray-700">
                                        Briskly:
                                        <strong class="font-semibold">{{ formatPrice(item.briskly_price) }}</strong>
                                    </span>
                                    <span
                                        v-if="isLargePriceDelta(item)"
                                        class="rounded bg-amber-100 px-1.5 py-0.5 text-xs font-medium text-amber-900"
                                    >
                                        Δ&gt;50%
                                    </span>
                                </div>
                            </div>
                        </li>
                    </ul>

                    <div
                        v-if="priceUpdateItems.length > 0"
                        class="mt-4 flex flex-wrap items-center gap-3 border-t border-gray-100 pt-4"
                    >
                        <button
                            type="button"
                            class="inline-flex items-center justify-center rounded-xl bg-max-primary px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-max-primary/90 disabled:cursor-not-allowed disabled:opacity-60"
                            :disabled="!canApplyPriceUpdates"
                            @click="applyPriceUpdates"
                        >
                            {{ applying ? 'Записываем…' : 'Изменить цены' }}
                        </button>
                        <p class="text-xs text-max-muted">
                            Отмечено UPDATE: {{ checkedPriceUpdateCount }}
                            <span v-if="checkedCreateCount > 0"> · CREATE: {{ checkedCreateCount }}</span>.
                            Равные цены не пишутся. Один apply на сессию.
                        </p>
                    </div>
                </div>

                <!-- Секция B: CREATE -->
                <div class="rounded-2xl border border-gray-100 bg-white p-4 shadow-sm">
                    <div class="mb-3 flex flex-wrap items-baseline justify-between gap-2">
                        <h3 class="text-sm font-semibold text-gray-900">
                            Создать в Briskly
                        </h3>
                        <span class="text-xs text-max-muted">
                            Показано {{ createShown }} из {{ createTotal }}
                        </span>
                    </div>

                    <div
                        v-if="createTruncationMessage"
                        class="mb-3 rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-900"
                        role="status"
                    >
                        {{ createTruncationMessage }}
                    </div>

                    <div
                        v-if="brisklyCategoriesError"
                        class="mb-3 rounded-xl border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700"
                    >
                        {{ brisklyCategoriesError }}
                    </div>

                    <p
                        v-if="brisklyCategoriesLoading"
                        class="mb-3 text-xs text-max-muted"
                    >
                        Загрузка категорий Briskly…
                    </p>

                    <p
                        v-if="createItems.length === 0"
                        class="text-sm text-max-muted"
                    >
                        Нет позиций только в VPS для создания.
                    </p>

                    <ul
                        v-else
                        class="divide-y divide-gray-100"
                    >
                        <li
                            v-for="item in createItems"
                            :key="item.line_key"
                            class="flex flex-col gap-2 py-3 first:pt-0 last:pb-0 sm:flex-row sm:gap-3"
                        >
                            <div class="flex min-w-0 flex-1 gap-3">
                                <input
                                    :id="`briskly-create-${item.line_key}`"
                                    type="checkbox"
                                    class="mt-1 h-4 w-4 shrink-0 rounded border-gray-300 text-max-primary focus:ring-max-primary/30 disabled:opacity-60"
                                    :checked="createChecked[item.line_key] === true"
                                    :disabled="resultsLocked"
                                    @change="onCreateCheckChange(item.line_key, $event)"
                                >
                                <div class="min-w-0 flex-1">
                                    <label
                                        class="block text-sm font-medium text-gray-900"
                                        :for="`briskly-create-${item.line_key}`"
                                    >
                                        {{ item.display_name }}
                                    </label>
                                    <p class="mt-1 text-xs text-gray-700 sm:text-sm">
                                        Цена VPS:
                                        <strong class="font-semibold">{{ formatPrice(item.source_price) }}</strong>
                                    </p>
                                </div>
                            </div>
                            <div class="w-full shrink-0 sm:w-48">
                                <label
                                    class="mb-1 block text-xs font-medium text-gray-700 sm:sr-only"
                                    :for="`briskly-create-cat-${item.line_key}`"
                                >
                                    Категория Briskly
                                </label>
                                <AppSelect
                                    :id="`briskly-create-cat-${item.line_key}`"
                                    size="sm"
                                    :model-value="createCategoryByLine[item.line_key] ?? ''"
                                    :options="brisklyCategorySelectOptions"
                                    :disabled="resultsLocked || brisklyCategoriesLoading || brisklyCategorySelectOptions.length <= 1"
                                    :invalid="isCreateCategoryMissing(item.line_key)"
                                    placeholder="Категория Briskly"
                                    @update:model-value="setCreateCategory(item.line_key, $event)"
                                />
                                <p
                                    v-if="isCreateCategoryMissing(item.line_key)"
                                    class="mt-1 text-xs text-rose-600"
                                >
                                    Обязательна для отмеченной строки
                                </p>
                            </div>
                        </li>
                    </ul>

                    <div
                        v-if="createItems.length > 0"
                        class="mt-4 flex flex-wrap items-center gap-3 border-t border-gray-100 pt-4"
                    >
                        <button
                            type="button"
                            class="inline-flex items-center justify-center rounded-xl bg-max-primary px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-max-primary/90 disabled:cursor-not-allowed disabled:opacity-60"
                            :disabled="!canApplyCreates"
                            @click="applyCreates"
                        >
                            {{ applying ? 'Записываем…' : 'Создать в Briskly' }}
                        </button>
                        <p class="text-xs text-max-muted">
                            Отмечено CREATE: {{ checkedCreateCount }}
                            <span v-if="checkedPriceUpdateCount > 0"> · UPDATE: {{ checkedPriceUpdateCount }}</span>.
                            Категория Briskly — только здесь. Один apply на сессию.
                        </p>
                    </div>
                </div>
            </section>
        </div>
    </div>
</template>
