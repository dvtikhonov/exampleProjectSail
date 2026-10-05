/**
 * UI синхронизации Briskly: поиск → match → результаты → apply (UPDATE / CREATE).
 * Галочки apply по умолчанию выключены; equal/briskly-only не пишем.
 */
import { computed, ref, watch } from 'vue';
import {
    applyBrisklySyncSession,
    createBrisklySyncSession,
    fetchBrisklyCategories,
    fetchBrisklySyncRestaurants,
    fetchBrisklySyncResults,
    fetchBrisklySyncSession,
    fetchBrisklySyncVpsCategories,
    loadBrisklySyncSnapshot,
    matchBrisklySyncSession,
    updateBrisklySyncApprovals,
} from '../api/admin/brisklySync';
import { extractErrorMessage } from '../api';

/**
 * @typedef {import('../api/admin/brisklySync.js').BrisklySyncPriceDiffItem} BrisklySyncPriceDiffItem
 * @typedef {import('../api/admin/brisklySync.js').BrisklySyncCreateItem} BrisklySyncCreateItem
 * @typedef {import('../api/admin/brisklySync.js').BrisklyCategory} BrisklyCategory
 * @typedef {import('../api/admin/brisklySync.js').BrisklySyncApplyReport} BrisklySyncApplyReport
 * @typedef {import('../api/admin/brisklySync.js').BrisklySyncApprovalsPayload} BrisklySyncApprovalsPayload
 */

/** Порог относительной Δ цены (как на сервере: confirm_large_delta). */
const LARGE_DELTA_RATIO = 0.5;

/** Интервал опроса GET /sessions/{id} пока status=matching. */
const MATCH_POLL_INTERVAL_MS = 2000;

/** Предел ожидания match (UI); job/nginx больше не держат HTTP POST. */
const MATCH_POLL_MAX_MS = 10 * 60 * 1000;

export function useBrisklySync() {
    /** @type {import('vue').Ref<{ id: number, name: string }[]>} */
    const restaurants = ref([]);
    const restaurantsLoading = ref(false);
    const restaurantsError = ref('');

    /** @type {import('vue').Ref<{ id: number, name: string }[]>} */
    const vpsCategories = ref([]);
    const vpsCategoriesLoading = ref(false);
    const vpsCategoriesError = ref('');

    /** @type {import('vue').Ref<string>} */
    const restaurantId = ref('');
    /** @type {import('vue').Ref<string>} '' = все категории */
    const vpsCategoryId = ref('');
    /** @type {import('vue').Ref<string>} */
    const searchText = ref('');
    /** @type {import('vue').Ref<string>} */
    const clarification = ref('');

    /** @type {import('vue').Ref<string|null>} */
    const sessionId = ref(null);
    /** @type {import('vue').Ref<string|null>} */
    const sessionStatus = ref(null);

    const searching = ref(false);
    const searchError = ref('');
    const searchProgress = ref('');

    /** @type {import('vue').Ref<BrisklySyncPriceDiffItem[]>} */
    const priceUpdateItems = ref([]);
    const priceUpdateTotal = ref(0);
    const priceUpdateShown = ref(0);
    const priceUpdateTruncated = ref(false);

    /** @type {import('vue').Ref<BrisklySyncCreateItem[]>} */
    const createItems = ref([]);
    const createTotal = ref(0);
    const createShown = ref(0);
    const createTruncated = ref(false);

    const skippedBrisklyOnly = ref(0);
    const ambiguousCount = ref(0);
    const equalPriceCount = ref(0);

    /** Успешно загружены sync-results (даже если обе секции пустые) */
    const resultsReady = ref(false);

    /** @type {import('vue').Ref<Record<string, boolean>>} line_key → apply (default off) */
    const priceUpdateChecked = ref({});
    /** @type {import('vue').Ref<Record<string, boolean>>} */
    const createChecked = ref({});
    /** Общая категория Briskly для всей группы CREATE */
    const groupBrisklyCategoryId = ref('');
    /** @type {import('vue').Ref<Record<string, string>>} line_key → briskly_category_id */
    const createCategoryByLine = ref({});

    /** @type {import('vue').Ref<BrisklyCategory[]>} */
    const brisklyCategories = ref([]);
    const brisklyCategoriesLoading = ref(false);
    const brisklyCategoriesError = ref('');

    const applying = ref(false);
    const applyError = ref('');
    /** @type {import('vue').Ref<BrisklySyncApplyReport|null>} */
    const applyReport = ref(null);
    const sessionApplied = ref(false);

    const hasResults = computed(() => resultsReady.value);

    const restaurantSelectOptions = computed(() => [
        { value: '', label: 'Выберите ресторан', disabled: true },
        ...restaurants.value.map((restaurant) => ({
            value: String(restaurant.id),
            label: restaurant.name,
        })),
    ]);

    const vpsCategorySelectOptions = computed(() => [
        { value: '', label: 'Все категории' },
        ...vpsCategories.value.map((category) => ({
            value: String(category.id),
            label: category.name,
        })),
    ]);

    const brisklyCategorySelectOptions = computed(() => [
        { value: '', label: 'Категория Briskly', disabled: true },
        ...brisklyCategories.value.map((category) => ({
            value: String(category.id),
            label: category.name,
        })),
    ]);

    const canSearch = computed(() => (
        restaurantId.value !== ''
        && !restaurantsLoading.value
        && !searching.value
        && !applying.value
    ));

    const priceUpdateTruncationMessage = computed(() => {
        if (!priceUpdateTruncated.value) {
            return '';
        }

        return `Найдено ${priceUpdateTotal.value}, показано ${priceUpdateShown.value} — сузьте фильтры.`;
    });

    const createTruncationMessage = computed(() => {
        if (!createTruncated.value) {
            return '';
        }

        return `Найдено ${createTotal.value}, показано ${createShown.value} — сузьте фильтры.`;
    });

    const checkedPriceUpdateCount = computed(() => (
        priceUpdateItems.value.filter((item) => priceUpdateChecked.value[item.line_key] === true).length
    ));

    const checkedCreateCount = computed(() => (
        createItems.value.filter((item) => createChecked.value[item.line_key] === true).length
    ));

    const canApplyPriceUpdates = computed(() => (
        resultsReady.value
        && !sessionApplied.value
        && !searching.value
        && !applying.value
        && Boolean(sessionId.value)
        && checkedPriceUpdateCount.value > 0
    ));

    const canApplyCreates = computed(() => (
        resultsReady.value
        && !sessionApplied.value
        && !searching.value
        && !applying.value
        && Boolean(sessionId.value)
        && checkedCreateCount.value > 0
    ));

    const resultsLocked = computed(() => sessionApplied.value || applying.value);

    async function loadRestaurants() {
        restaurantsLoading.value = true;
        restaurantsError.value = '';

        try {
            restaurants.value = await fetchBrisklySyncRestaurants();
        } catch (error) {
            restaurants.value = [];
            restaurantsError.value = extractErrorMessage(error);
        } finally {
            restaurantsLoading.value = false;
        }
    }

    /**
     * @param {string} id
     */
    async function loadVpsCategories(id) {
        vpsCategories.value = [];
        vpsCategoriesError.value = '';

        if (!id) {
            return;
        }

        const restaurantNumericId = Number(id);

        if (!Number.isFinite(restaurantNumericId) || restaurantNumericId < 1) {
            return;
        }

        vpsCategoriesLoading.value = true;

        try {
            vpsCategories.value = await fetchBrisklySyncVpsCategories(restaurantNumericId);
        } catch (error) {
            vpsCategories.value = [];
            vpsCategoriesError.value = extractErrorMessage(error);
        } finally {
            vpsCategoriesLoading.value = false;
        }
    }

    watch(restaurantId, (id) => {
        vpsCategoryId.value = '';
        loadVpsCategories(id);
    });

    /**
     * @param {string} value
     */
    function setRestaurantId(value) {
        restaurantId.value = value;
    }

    /**
     * @param {string} value
     */
    function setVpsCategoryId(value) {
        vpsCategoryId.value = value;
    }

    /**
     * @param {string} value
     */
    function setSearchText(value) {
        searchText.value = value.slice(0, 120);
    }

    /**
     * @param {string} value
     */
    function setClarification(value) {
        clarification.value = value.slice(0, 2000);
    }

    /**
     * @param {string} lineKey
     * @param {boolean} checked
     */
    function setPriceUpdateChecked(lineKey, checked) {
        if (resultsLocked.value) {
            return;
        }

        priceUpdateChecked.value = {
            ...priceUpdateChecked.value,
            [lineKey]: checked === true,
        };
    }

    /**
     * @param {string} lineKey
     * @param {boolean} checked
     */
    function setCreateChecked(lineKey, checked) {
        if (resultsLocked.value) {
            return;
        }

        createChecked.value = {
            ...createChecked.value,
            [lineKey]: checked === true,
        };
    }

    /**
     * Задаёт категорию Briskly для всей группы CREATE (всем строкам).
     *
     * @param {string} categoryId
     */
    function setGroupBrisklyCategoryId(categoryId) {
        if (resultsLocked.value) {
            return;
        }

        const nextId = String(categoryId ?? '');
        groupBrisklyCategoryId.value = nextId;

        /** @type {Record<string, string>} */
        const categories = {};

        for (const item of createItems.value) {
            categories[item.line_key] = nextId;
        }

        createCategoryByLine.value = categories;
    }

    /**
     * Есть отмеченные CREATE без выбранной групповой категории.
     *
     * @returns {boolean}
     */
    function isGroupBrisklyCategoryMissing() {
        return checkedCreateCount.value > 0
            && String(groupBrisklyCategoryId.value ?? '').trim() === '';
    }

    function resetResults() {
        resultsReady.value = false;
        priceUpdateItems.value = [];
        priceUpdateTotal.value = 0;
        priceUpdateShown.value = 0;
        priceUpdateTruncated.value = false;
        createItems.value = [];
        createTotal.value = 0;
        createShown.value = 0;
        createTruncated.value = false;
        skippedBrisklyOnly.value = 0;
        ambiguousCount.value = 0;
        equalPriceCount.value = 0;
        priceUpdateChecked.value = {};
        createChecked.value = {};
        groupBrisklyCategoryId.value = '';
        createCategoryByLine.value = {};
        brisklyCategories.value = [];
        brisklyCategoriesError.value = '';
        applyError.value = '';
        applyReport.value = null;
        sessionApplied.value = false;
    }

    /**
     * @param {BrisklySyncPriceDiffItem[]} items
     */
    function initPriceUpdateChecks(items) {
        /** @type {Record<string, boolean>} */
        const next = {};

        for (const item of items) {
            next[item.line_key] = false;
        }

        priceUpdateChecked.value = next;
    }

    /**
     * @param {BrisklySyncCreateItem[]} items
     */
    function initCreateChecks(items) {
        /** @type {Record<string, boolean>} */
        const checks = {};
        /** @type {Record<string, string>} */
        const categories = {};

        for (const item of items) {
            checks[item.line_key] = false;
            categories[item.line_key] = '';
        }

        groupBrisklyCategoryId.value = '';
        createChecked.value = checks;
        createCategoryByLine.value = categories;
    }

    async function loadCategoriesForCreates(id) {
        if (createItems.value.length === 0) {
            brisklyCategories.value = [];

            return;
        }

        brisklyCategoriesLoading.value = true;
        brisklyCategoriesError.value = '';

        try {
            brisklyCategories.value = await fetchBrisklyCategories(id);
        } catch (error) {
            brisklyCategories.value = [];
            brisklyCategoriesError.value = extractErrorMessage(error);
        } finally {
            brisklyCategoriesLoading.value = false;
        }
    }

    /**
     * @param {string|number} sourcePrice
     * @param {string|number} brisklyPrice
     * @returns {number}
     */
    function relativePriceDelta(sourcePrice, brisklyPrice) {
        const source = Number(sourcePrice);
        const briskly = Number(brisklyPrice);

        if (!Number.isFinite(source) || !Number.isFinite(briskly)) {
            return 0;
        }

        if (briskly === 0) {
            return source === 0 ? 0 : 1;
        }

        return Math.abs(source - briskly) / Math.abs(briskly);
    }

    /**
     * @param {BrisklySyncPriceDiffItem} item
     * @returns {boolean}
     */
    function isLargePriceDelta(item) {
        return relativePriceDelta(item.source_price, item.briskly_price) > LARGE_DELTA_RATIO;
    }

    /**
     * Собирает approvals из текущего UI (equal не попадает — их нет в price_updates).
     *
     * @param {{ confirmLargeDeltaKeys?: Set<string> }} [options]
     * @returns {BrisklySyncApprovalsPayload}
     */
    function buildApprovalsPayload({ confirmLargeDeltaKeys = new Set() } = {}) {
        return {
            price_updates: priceUpdateItems.value.map((item) => ({
                line_key: item.line_key,
                apply: priceUpdateChecked.value[item.line_key] === true,
                confirm_large_delta: confirmLargeDeltaKeys.has(item.line_key),
            })),
            creates: createItems.value.map((item) => {
                const apply = createChecked.value[item.line_key] === true;
                const categoryRaw = String(createCategoryByLine.value[item.line_key] ?? '').trim();
                const categoryId = categoryRaw === '' ? null : Number(categoryRaw);

                return {
                    line_key: item.line_key,
                    apply,
                    briskly_category_id: apply && Number.isFinite(categoryId) && categoryId >= 1
                        ? categoryId
                        : null,
                };
            }),
        };
    }

    /**
     * @returns {string|null} сообщение об ошибке валидации CREATE или null
     */
    function validateCheckedCreates() {
        if (!isGroupBrisklyCategoryMissing()) {
            return null;
        }

        return 'Для отмеченных позиций выберите категорию Briskly.';
    }

    /**
     * Подтверждение Δ>50% для отмеченных UPDATE.
     *
     * @returns {Set<string>|null} ключи с confirm или null если пользователь отменил
     */
    function confirmLargeDeltasIfNeeded() {
        /** @type {Set<string>} */
        const confirmed = new Set();
        const largeItems = priceUpdateItems.value.filter((item) => (
            priceUpdateChecked.value[item.line_key] === true
            && isLargePriceDelta(item)
        ));

        if (largeItems.length === 0) {
            return confirmed;
        }

        const preview = largeItems
            .slice(0, 5)
            .map((item) => `• ${item.display_name}: ${item.briskly_price} → ${item.source_price}`)
            .join('\n');
        const more = largeItems.length > 5 ? `\n…и ещё ${largeItems.length - 5}` : '';
        const ok = typeof window !== 'undefined'
            && typeof window.confirm === 'function'
            && window.confirm(
                `У ${largeItems.length} позици${largeItems.length === 1 ? 'и' : 'й'} изменение цены больше 50%.\n\n`
                + `${preview}${more}\n\nПродолжить запись в Briskly?`,
            );

        if (!ok) {
            return null;
        }

        for (const item of largeItems) {
            confirmed.add(item.line_key);
        }

        return confirmed;
    }

    /**
     * @param {'prices'|'creates'} mode
     */
    async function runApply(mode) {
        if (!sessionId.value || sessionApplied.value || applying.value) {
            return;
        }

        applyError.value = '';

        if (mode === 'prices' && checkedPriceUpdateCount.value === 0) {
            applyError.value = 'Отметьте позиции для изменения цен.';

            return;
        }

        if (mode === 'creates' && checkedCreateCount.value === 0) {
            applyError.value = 'Отметьте позиции для создания в Briskly.';

            return;
        }

        // В одном apply уходят все отмеченные строки обеих секций (лимит 25+25).
        if (checkedCreateCount.value > 0) {
            const createValidationError = validateCheckedCreates();

            if (createValidationError) {
                applyError.value = createValidationError;

                return;
            }
        }

        if (checkedPriceUpdateCount.value === 0 && checkedCreateCount.value === 0) {
            applyError.value = 'Нет отмеченных позиций для записи.';

            return;
        }

        const confirmKeys = checkedPriceUpdateCount.value > 0
            ? confirmLargeDeltasIfNeeded()
            : new Set();

        if (confirmKeys === null) {
            applyError.value = 'Запись отменена: требуется подтверждение большого изменения цены.';

            return;
        }

        applying.value = true;

        try {
            // Один apply на сессию: в payload — все отмеченные UPDATE и CREATE.
            // Кнопка валидирует «свою» секцию; отмеченное в другой тоже уходит в тот же run.
            const payload = buildApprovalsPayload({ confirmLargeDeltaKeys: confirmKeys });

            const approved = await updateBrisklySyncApprovals(sessionId.value, payload);
            sessionStatus.value = approved.status;

            const report = await applyBrisklySyncSession(sessionId.value);
            applyReport.value = report;
            sessionApplied.value = true;
            sessionStatus.value = 'applied';
        } catch (error) {
            applyError.value = extractErrorMessage(error);
        } finally {
            applying.value = false;
        }
    }

    /**
     * «Изменить цены» — отмеченные UPDATE (+ отмеченные CREATE в том же apply).
     * Equal не в results и UPDATE для них не вызывается.
     */
    async function applyPriceUpdates() {
        await runApply('prices');
    }

    /**
     * «Создать в Briskly» — отмеченные CREATE с категорией (+ отмеченные UPDATE в том же apply).
     */
    async function applyCreates() {
        await runApply('creates');
    }

    /**
     * Создать сессию → snapshot → match → sync-results (без apply).
     */
    async function runSearch() {
        if (!canSearch.value) {
            return;
        }

        searching.value = true;
        searchError.value = '';
        searchProgress.value = 'Создание сессии…';
        sessionId.value = null;
        sessionStatus.value = null;
        resetResults();

        try {
            const restaurantNumericId = Number(restaurantId.value);
            const categoryRaw = vpsCategoryId.value.trim();
            const categoryNumeric = categoryRaw === '' ? null : Number(categoryRaw);

            const session = await createBrisklySyncSession({
                restaurantId: restaurantNumericId,
                vpsCategoryId: categoryNumeric !== null && Number.isFinite(categoryNumeric)
                    ? categoryNumeric
                    : null,
                searchText: searchText.value,
                clarification: clarification.value,
            });

            sessionId.value = session.id;
            sessionStatus.value = session.status;

            searchProgress.value = 'Загрузка каталога Briskly…';
            const snapshot = await loadBrisklySyncSnapshot(session.id);
            sessionStatus.value = snapshot.session.status;

            searchProgress.value = 'Сопоставление позиций…';
            const accepted = await matchBrisklySyncSession(session.id);
            sessionStatus.value = accepted.status;

            const matched = await waitForMatchCompletion(session.id);
            sessionStatus.value = matched.status;

            if (matched.status === 'failed') {
                throw new Error('Сопоставление не удалось. Повторите поиск.');
            }

            if (matched.status !== 'matched') {
                throw new Error(`Неожиданный статус сессии: ${matched.status}`);
            }

            searchProgress.value = 'Загрузка результатов…';
            const results = await fetchBrisklySyncResults(session.id);

            priceUpdateItems.value = results.price_updates.items;
            priceUpdateTotal.value = results.price_updates.total;
            priceUpdateShown.value = results.price_updates.shown;
            priceUpdateTruncated.value = results.price_updates.truncated;

            createItems.value = results.creates.items;
            createTotal.value = results.creates.total;
            createShown.value = results.creates.shown;
            createTruncated.value = results.creates.truncated;

            skippedBrisklyOnly.value = results.counts.skipped_briskly_only;
            ambiguousCount.value = results.counts.ambiguous;
            equalPriceCount.value = results.counts.equal_price;

            initPriceUpdateChecks(priceUpdateItems.value);
            initCreateChecks(createItems.value);

            await loadCategoriesForCreates(session.id);
            resultsReady.value = true;
            searchProgress.value = '';
        } catch (error) {
            searchError.value = extractErrorMessage(error);
            searchProgress.value = '';
            resultsReady.value = false;
        } finally {
            searching.value = false;
        }
    }

    /**
     * @param {string} id
     * @returns {Promise<import('../api/admin/brisklySync.js').BrisklySyncSessionMeta>}
     */
    async function waitForMatchCompletion(id) {
        const deadline = Date.now() + MATCH_POLL_MAX_MS;

        while (Date.now() < deadline) {
            const session = await fetchBrisklySyncSession(id);
            sessionStatus.value = session.status;

            if (session.status === 'matched' || session.status === 'failed') {
                return session;
            }

            if (session.status !== 'matching') {
                return session;
            }

            searchProgress.value = 'Сопоставление позиций…';
            await delay(MATCH_POLL_INTERVAL_MS);
        }

        throw new Error('Сопоставление не завершилось вовремя. Проверьте статус сессии позже.');
    }

    /**
     * @param {number} ms
     * @returns {Promise<void>}
     */
    function delay(ms) {
        return new Promise((resolve) => {
            setTimeout(resolve, ms);
        });
    }

    return {
        restaurants,
        restaurantsLoading,
        restaurantsError,
        vpsCategories,
        vpsCategoriesLoading,
        vpsCategoriesError,
        restaurantId,
        vpsCategoryId,
        searchText,
        clarification,
        sessionId,
        sessionStatus,
        searching,
        searchError,
        searchProgress,
        priceUpdateItems,
        priceUpdateTotal,
        priceUpdateShown,
        priceUpdateTruncated,
        priceUpdateTruncationMessage,
        createItems,
        createTotal,
        createShown,
        createTruncated,
        createTruncationMessage,
        skippedBrisklyOnly,
        ambiguousCount,
        equalPriceCount,
        priceUpdateChecked,
        createChecked,
        groupBrisklyCategoryId,
        createCategoryByLine,
        brisklyCategories,
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
        setVpsCategoryId,
        setSearchText,
        setClarification,
        setPriceUpdateChecked,
        setCreateChecked,
        setGroupBrisklyCategoryId,
        isGroupBrisklyCategoryMissing,
        isLargePriceDelta,
        runSearch,
        applyPriceUpdates,
        applyCreates,
    };
}
