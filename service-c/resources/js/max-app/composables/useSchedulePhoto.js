/**
 * Фото графика: форма → readiness → OCR/match (poll) → сводка/Issues → confirm apply.
 */
import { computed, onScopeDispose, ref, watch } from 'vue';
import { extractErrorMessage } from '../api';
import {
    applySchedulePhotoRun,
    checkSchedulePhotoReadiness,
    fetchSchedulePhotoCatalog,
    fetchSchedulePhotoConfig,
    fetchSchedulePhotoRestaurants,
    fetchSchedulePhotoRun,
    resolveSchedulePhotoDiscarded,
    startSchedulePhotoRun,
} from '../api/admin/schedulePhoto';

/** @typedef {import('../api/admin/schedulePhoto.js').SchedulePhotoReadiness} SchedulePhotoReadiness */
/** @typedef {import('../api/admin/schedulePhoto.js').SchedulePhotoRun} SchedulePhotoRun */
/** @typedef {import('../api/admin/schedulePhoto.js').SchedulePhotoMatchResult} SchedulePhotoMatchResult */
/** @typedef {import('../api/admin/schedulePhoto.js').SchedulePhotoBaseUrlOption} SchedulePhotoBaseUrlOption */

/** Fallback до ответа GET /config (PHP в Docker → host.docker.internal). */
const FALLBACK_BASE_URL = 'http://host.docker.internal:8083';
const OCR_POLL_INTERVAL_MS = 2000;
const OCR_POLL_MAX_MS = 10 * 60 * 1000;

/** Превью и файл формы: только эти MIME. Пустой и чужой type отсекаются до createObjectURL. */
const ALLOWED_PHOTO_MIME_TYPES = new Set(['image/jpeg', 'image/png', 'image/webp']);
const PHOTO_TYPE_ERROR = 'Допустимы только JPEG, PNG и WebP.';

/**
 * @returns {string} YYYY-MM-DD (локальная дата)
 */
function todayIsoDate() {
    const now = new Date();
    const y = now.getFullYear();
    const m = String(now.getMonth() + 1).padStart(2, '0');
    const d = String(now.getDate()).padStart(2, '0');

    return `${y}-${m}-${d}`;
}

/**
 * @param {string} dateFrom
 * @returns {string}
 */
function dateToFrom(dateFrom) {
    const parts = dateFrom.split('-').map(Number);

    if (parts.length !== 3 || parts.some((n) => !Number.isFinite(n))) {
        return dateFrom;
    }

    const date = new Date(parts[0], parts[1] - 1, parts[2]);
    date.setDate(date.getDate() + 6);
    const y = date.getFullYear();
    const m = String(date.getMonth() + 1).padStart(2, '0');
    const d = String(date.getDate()).padStart(2, '0');

    return `${y}-${m}-${d}`;
}

/**
 * @param {string} dateFrom
 * @param {string} dateTo
 * @returns {string[]}
 */
function listDatesInWindow(dateFrom, dateTo) {
    const fromParts = dateFrom.split('-').map(Number);
    const toParts = dateTo.split('-').map(Number);

    if (
        fromParts.length !== 3
        || toParts.length !== 3
        || fromParts.some((n) => !Number.isFinite(n))
        || toParts.some((n) => !Number.isFinite(n))
    ) {
        return [];
    }

    /** @type {string[]} */
    const dates = [];
    const cursor = new Date(fromParts[0], fromParts[1] - 1, fromParts[2]);
    const end = new Date(toParts[0], toParts[1] - 1, toParts[2]);

    while (cursor <= end) {
        const y = cursor.getFullYear();
        const m = String(cursor.getMonth() + 1).padStart(2, '0');
        const d = String(cursor.getDate()).padStart(2, '0');
        dates.push(`${y}-${m}-${d}`);
        cursor.setDate(cursor.getDate() + 1);
    }

    return dates;
}

/**
 * @param {number} ms
 * @param {AbortSignal} [signal]
 * @returns {Promise<void>}
 */
function delay(ms, signal) {
    return new Promise((resolve, reject) => {
        if (signal?.aborted) {
            reject(pollAbortError());

            return;
        }

        /** @type {ReturnType<typeof setTimeout>|undefined} */
        let timer;

        const onAbort = () => {
            if (timer !== undefined) {
                clearTimeout(timer);
            }

            reject(pollAbortError());
        };

        timer = setTimeout(() => {
            signal?.removeEventListener('abort', onAbort);
            resolve();
        }, ms);

        signal?.addEventListener('abort', onAbort, { once: true });
    });
}

/**
 * @returns {DOMException}
 */
function pollAbortError() {
    return new DOMException('Опрос OCR прерван', 'AbortError');
}

/**
 * @param {unknown} value
 * @returns {string}
 */
function normalizeBaseUrl(value) {
    return String(value ?? '').trim().replace(/\/$/, '');
}

/**
 * Локальный Docker и loopback в тексте готовности не называются VPS.
 *
 * @param {string} url
 * @returns {boolean}
 */
function isLocalSchedulePhotoOrigin(url) {
    try {
        const host = new URL(url).hostname.toLowerCase();

        return host === 'host.docker.internal'
            || host === 'localhost'
            || host === '127.0.0.1';
    } catch {
        return false;
    }
}

/**
 * @param {unknown} list
 * @returns {{ id: number, name: string }[]}
 */
function normalizeNamedRows(list) {
    if (!Array.isArray(list)) {
        return [];
    }

    return list
        .filter((item) => item && Number(item.id) >= 1 && String(item.name || '').trim() !== '')
        .map((item) => ({
            id: Number(item.id),
            name: String(item.name).trim(),
        }));
}

/**
 * Отмена poll (уход со страницы) и axios CanceledError.
 *
 * @param {unknown} error
 * @returns {boolean}
 */
function isPollAborted(error) {
    if (!error || typeof error !== 'object') {
        return false;
    }

    const candidate = /** @type {{ name?: string, code?: string }} */ (error);

    return candidate.name === 'AbortError'
        || candidate.name === 'CanceledError'
        || candidate.code === 'ERR_CANCELED';
}

export function useSchedulePhoto() {
    const baseUrl = ref(FALLBACK_BASE_URL);
    /** @type {import('vue').Ref<SchedulePhotoBaseUrlOption[]>} */
    const baseUrlOptions = ref([
        { value: FALLBACK_BASE_URL, label: 'Локальный (host.docker.internal:8083)' },
    ]);
    const dateFrom = ref(todayIsoDate());
    /** @type {import('vue').Ref<string>} */
    const restaurantId = ref('');
    /** @type {import('vue').Ref<number[]>} */
    const selectedCategoryIds = ref([]);
    /** @type {import('vue').Ref<File|null>} */
    const photoFile = ref(null);
    /** @type {import('vue').Ref<string|null>} */
    const photoPreviewUrl = ref(null);
    const photoError = ref('');

    /** @type {import('vue').Ref<{ id: number, name: string }[]>} */
    const restaurants = ref([]);
    /** @type {import('vue').Ref<{ id: number, name: string }[]>} */
    const categories = ref([]);
    const catalogLoading = ref(false);
    const catalogError = ref('');
    const restaurantsLoading = ref(false);
    const categoriesLoading = ref(false);
    const categoriesError = ref('');
    let restaurantsRequestSeq = 0;
    let categoriesRequestSeq = 0;
    /** Смена BASE_URL из GET /config не должна грузить рестораны дважды. */
    let skipBaseUrlRestaurantReload = 0;

    /** @type {import('vue').Ref<SchedulePhotoReadiness|null>} */
    const readiness = ref(null);
    const readinessLoading = ref(false);
    const readinessError = ref('');
    const readinessOk = ref(false);

    const running = ref(false);
    const runProgress = ref('');
    const runError = ref('');
    let disposed = false;
    /** @type {AbortController|null} */
    let pollAbortController = null;

    /** @type {import('vue').Ref<SchedulePhotoRun|null>} */
    const run = ref(null);

    const applying = ref(false);
    const applyError = ref('');
    const applied = ref(false);

    const resolvingDiscarded = ref(false);
    const resolveDiscardedError = ref('');
    /** @type {import('vue').Ref<Record<string, string>>} */
    const discardedResolveDishName = ref({});
    /** Ручной выбор дня, если OCR не вернул dates. Ключ — raw_title. */
    /** @type {import('vue').Ref<Record<string, string[]>>} */
    const discardedResolveDates = ref({});

    const restaurantOptions = computed(() => (
        [...restaurants.value].sort((a, b) => a.name.localeCompare(b.name, 'ru'))
    ));

    const restaurantSelectOptions = computed(() => [
        { value: '', label: 'Выберите ресторан', disabled: true },
        ...restaurantOptions.value.map((restaurant) => ({
            value: String(restaurant.id),
            label: restaurant.name,
        })),
    ]);

    const categoryOptions = computed(() => (
        [...categories.value].sort((a, b) => a.name.localeCompare(b.name, 'ru'))
    ));

    const selectedRestaurantName = computed(() => {
        const rid = restaurantId.value ? Number(restaurantId.value) : null;

        if (rid === null) {
            return '';
        }

        return restaurantOptions.value.find((r) => r.id === rid)?.name ?? '';
    });

    const selectedCategoryNames = computed(() => {
        const byId = new Map(categoryOptions.value.map((c) => [c.id, c.name]));

        return selectedCategoryIds.value
            .map((id) => ({ id, name: byId.get(id) ?? `id ${id}` }));
    });

    const windowDateFrom = computed(() => run.value?.date_from || dateFrom.value);

    const windowDateTo = computed(() => {
        if (run.value?.date_to) {
            return run.value.date_to;
        }

        return dateToFrom(dateFrom.value);
    });

    const matchResult = computed(() => run.value?.match_result ?? null);

    const matchedCount = computed(() => Number(matchResult.value?.matched_count ?? 0));

    const matchedItems = computed(() => matchResult.value?.matched ?? []);

    const issues = computed(() => matchResult.value?.issues ?? []);

    const discarded = computed(() => run.value?.discarded ?? []);

    const catalogScope = computed(() => run.value?.catalog_scope ?? []);

    const catalogScopeSelectOptions = computed(() => [
        { value: '', label: 'Выберите блюдо из каталога', disabled: true },
        ...catalogScope.value.map((dish) => ({
            value: dish.name,
            label: dish.category_name
                ? `${dish.name} (${dish.category_name})`
                : dish.name,
        })),
    ]);

    const windowDates = computed(() => listDatesInWindow(
        windowDateFrom.value,
        windowDateTo.value,
    ));

    /** Matcher: нет в каталоге scope. */
    const issuesNotFound = computed(() => (
        issues.value.filter((issue) => issue.code === 'dish_not_found')
    ));

    /** Matcher: >1 кандидат в scope. */
    const issuesAmbiguous = computed(() => (
        issues.value.filter((issue) => issue.code === 'dish_ambiguous')
    ));

    /** Прочие коды matcher (напр. combo_unresolved). */
    const issuesOther = computed(() => (
        issues.value.filter((issue) => (
            issue.code !== 'dish_not_found'
            && issue.code !== 'dish_ambiguous'
        ))
    ));

    const hasUnresolvedRows = computed(() => (
        issues.value.length > 0 || discarded.value.length > 0
    ));

    const hasMatchResult = computed(() => (
        run.value !== null
        && (run.value.status === 'matched' || run.value.status === 'applied')
        && matchResult.value !== null
    ));

    /** matched_count = 0 → STOP, apply недоступен. */
    const isZeroMatchStop = computed(() => (
        hasMatchResult.value
        && matchedCount.value === 0
        && !applied.value
    ));

    /** Частичный матч: есть matched и есть issues/discarded. */
    const isPartialMatch = computed(() => (
        matchedCount.value > 0
        && hasUnresolvedRows.value
    ));

    const canStart = computed(() => (
        readinessOk.value
        && !running.value
        && !applying.value
        && !applied.value
        && baseUrl.value.trim() !== ''
        && dateFrom.value !== ''
        && restaurantId.value !== ''
        && selectedCategoryIds.value.length > 0
        && photoFile.value !== null
    ));

    const readinessOkMessage = computed(() => {
        const photoTextLabel = isLocalSchedulePhotoOrigin(baseUrl.value)
            ? 'локальный PhotoText'
            : 'VPS PhotoText';

        return `Готовность OK: AI, ${photoTextLabel} и sidecar доступны.`;
    });

    const canApply = computed(() => (
        hasMatchResult.value
        && !applied.value
        && !applying.value
        && !running.value
        && !resolvingDiscarded.value
        && matchedCount.value > 0
        && run.value?.status === 'matched'
    ));

    const formLocked = computed(() => (
        running.value
        || applying.value
        || resolvingDiscarded.value
        || applied.value
        || (run.value !== null && (run.value.status === 'matched' || run.value.status === 'pending' || run.value.status === 'ocr_running'))
    ));

    function revokePhotoPreview() {
        if (photoPreviewUrl.value) {
            URL.revokeObjectURL(photoPreviewUrl.value);
            photoPreviewUrl.value = null;
        }

        photoError.value = '';
    }

    /**
     * @param {string} mimeType
     * @returns {boolean}
     */
    function isAllowedSchedulePhotoMime(mimeType) {
        return ALLOWED_PHOTO_MIME_TYPES.has(mimeType);
    }

    /**
     * Чужой или пустой MIME не кладётся в форму и не декодируется через blob-URL.
     *
     * @param {File|null} file
     */
    function setPhotoFile(file) {
        revokePhotoPreview();

        if (file !== null && !isAllowedSchedulePhotoMime(file.type)) {
            photoFile.value = null;
            photoError.value = PHOTO_TYPE_ERROR;

            return;
        }

        photoFile.value = file;

        if (file) {
            photoPreviewUrl.value = URL.createObjectURL(file);
        }
    }

    function invalidateReadiness() {
        readinessOk.value = false;
        readiness.value = null;
        readinessError.value = '';
    }

    watch(baseUrl, () => {
        invalidateReadiness();

        if (skipBaseUrlRestaurantReload > 0) {
            skipBaseUrlRestaurantReload -= 1;

            return;
        }

        void refreshRestaurants();
    });

    watch(restaurantId, () => {
        selectedCategoryIds.value = [];
        void loadCategories();
    });

    function abortOcrPoll() {
        if (pollAbortController !== null) {
            pollAbortController.abort();
            pollAbortController = null;
        }
    }

    onScopeDispose(() => {
        disposed = true;
        abortOcrPoll();
        revokePhotoPreview();
    });

    const baseUrlSelectOptions = computed(() => (
        baseUrlOptions.value.map((option) => ({
            value: option.value,
            label: option.label,
        }))
    ));

    /**
     * @param {import('../api/admin/schedulePhoto.js').SchedulePhotoConfig|null|undefined} scheduleConfig
     */
    function applyScheduleConfig(scheduleConfig) {
        if (
            !scheduleConfig
            || !Array.isArray(scheduleConfig.allowed_base_urls)
            || scheduleConfig.allowed_base_urls.length === 0
        ) {
            return;
        }

        baseUrlOptions.value = scheduleConfig.allowed_base_urls
            .filter((option) => option && typeof option.value === 'string' && option.value.trim() !== '')
            .map((option) => ({
                value: normalizeBaseUrl(option.value),
                label: String(option.label || option.value),
            }));

        const allowedValues = new Set(baseUrlOptions.value.map((option) => option.value));
        const preferred = normalizeBaseUrl(scheduleConfig.default_base_url || '');
        let next = baseUrl.value;

        if (preferred !== '' && allowedValues.has(preferred)) {
            next = preferred;
        } else if (!allowedValues.has(baseUrl.value) && baseUrlOptions.value.length > 0) {
            next = baseUrlOptions.value[0].value;
        }

        if (next !== baseUrl.value) {
            skipBaseUrlRestaurantReload += 1;
            baseUrl.value = next;
        }
    }

    async function loadRestaurants() {
        const requestId = ++restaurantsRequestSeq;
        const url = normalizeBaseUrl(baseUrl.value);

        restaurantId.value = '';
        selectedCategoryIds.value = [];
        categories.value = [];
        categoriesError.value = '';
        restaurants.value = [];

        if (url === '') {
            restaurantsLoading.value = false;
            catalogError.value = 'Укажите BASE_URL.';

            return;
        }

        restaurantsLoading.value = true;
        catalogError.value = '';

        try {
            const list = await fetchSchedulePhotoRestaurants(url);

            if (requestId !== restaurantsRequestSeq || normalizeBaseUrl(baseUrl.value) !== url) {
                return;
            }

            restaurants.value = normalizeNamedRows(list);
        } catch (error) {
            if (requestId !== restaurantsRequestSeq) {
                return;
            }

            catalogError.value = extractErrorMessage(error);
            restaurants.value = [];
        } finally {
            if (requestId === restaurantsRequestSeq) {
                restaurantsLoading.value = false;
            }
        }
    }

    async function refreshRestaurants() {
        await loadRestaurants();
    }

    async function loadCategories() {
        const requestId = ++categoriesRequestSeq;
        const url = normalizeBaseUrl(baseUrl.value);
        const rid = restaurantId.value ? Number(restaurantId.value) : 0;

        categories.value = [];
        categoriesError.value = '';

        if (url === '' || !Number.isInteger(rid) || rid < 1) {
            categoriesLoading.value = false;

            return;
        }

        categoriesLoading.value = true;

        try {
            const catalog = await fetchSchedulePhotoCatalog(url, rid);

            if (
                requestId !== categoriesRequestSeq
                || normalizeBaseUrl(baseUrl.value) !== url
                || Number(restaurantId.value) !== rid
            ) {
                return;
            }

            categories.value = normalizeNamedRows(catalog?.categories);
        } catch (error) {
            if (requestId !== categoriesRequestSeq) {
                return;
            }

            categoriesError.value = extractErrorMessage(error);
            categories.value = [];
        } finally {
            if (requestId === categoriesRequestSeq) {
                categoriesLoading.value = false;
            }
        }
    }

    function reloadCategories() {
        void loadCategories();
    }

    async function loadCatalog() {
        catalogLoading.value = true;
        catalogError.value = '';

        /** @type {string} */
        let configError = '';

        try {
            const scheduleConfig = await fetchSchedulePhotoConfig();
            applyScheduleConfig(scheduleConfig);
        } catch (error) {
            configError = extractErrorMessage(error);
        } finally {
            catalogLoading.value = false;
        }

        await loadRestaurants();

        if (configError !== '') {
            catalogError.value = catalogError.value !== ''
                ? `${configError} ${catalogError.value}`
                : configError;
        }
    }

    /**
     * @param {string} value
     */
    function setBaseUrl(value) {
        baseUrl.value = normalizeBaseUrl(value);
    }

    /**
     * @param {string} value
     */
    function setDateFrom(value) {
        dateFrom.value = value;
    }

    /**
     * @param {string} value
     */
    function setRestaurantId(value) {
        restaurantId.value = value;
    }

    /**
     * @param {number} categoryId
     * @param {boolean} checked
     */
    function setCategoryChecked(categoryId, checked) {
        const current = new Set(selectedCategoryIds.value);

        if (checked) {
            current.add(categoryId);
        } else {
            current.delete(categoryId);
        }

        selectedCategoryIds.value = [...current].sort((a, b) => a - b);
    }

    /**
     * @param {boolean} checked
     */
    function setAllCategoriesChecked(checked) {
        if (!checked) {
            selectedCategoryIds.value = [];

            return;
        }

        selectedCategoryIds.value = categoryOptions.value.map((c) => c.id);
    }

    async function checkReadiness() {
        if (readinessLoading.value || running.value || applying.value) {
            return;
        }

        const url = baseUrl.value.trim();

        if (url === '') {
            readinessError.value = 'Укажите BASE_URL.';
            readinessOk.value = false;

            return;
        }

        readinessLoading.value = true;
        readinessError.value = '';
        readiness.value = null;
        readinessOk.value = false;

        try {
            const result = await checkSchedulePhotoReadiness(url);
            readiness.value = result;
            readinessOk.value = result.ok === true;

            if (!result.ok) {
                readinessError.value = buildReadinessFailureMessage(result);
            }
        } catch (error) {
            readinessOk.value = false;
            readiness.value = null;
            readinessError.value = extractErrorMessage(error);
        } finally {
            readinessLoading.value = false;
        }
    }

    /**
     * @param {SchedulePhotoReadiness} result
     * @returns {string}
     */
    function buildReadinessFailureMessage(result) {
        /** @type {string[]} */
        const parts = [];

        if (!result.ai_access?.ok) {
            parts.push(result.ai_access?.message || 'Нет доступа AI');
        }

        if (!result.phototext?.ok) {
            const fallback = isLocalSchedulePhotoOrigin(baseUrl.value)
                ? 'Локальный PhotoText недоступен'
                : 'VPS PhotoText недоступен';
            parts.push(result.phototext?.message || fallback);
        }

        if (!result.sidecar?.ok) {
            parts.push(result.sidecar?.message || 'Sidecar LLM не запущен');
        }

        return parts.join(' · ') || 'Проверка готовности не пройдена.';
    }

    async function startRun() {
        if (!canStart.value || photoFile.value === null) {
            return;
        }

        running.value = true;
        runError.value = '';
        applyError.value = '';
        applied.value = false;
        run.value = null;
        runProgress.value = 'Запуск OCR…';

        try {
            const created = await startSchedulePhotoRun({
                baseUrl: baseUrl.value.trim(),
                dateFrom: dateFrom.value,
                restaurantId: Number(restaurantId.value),
                categoryIds: [...selectedCategoryIds.value],
                photo: photoFile.value,
            });

            run.value = created;
            runProgress.value = 'Распознавание фото…';

            const finished = await waitForRunCompletion(created.id);
            run.value = finished;

            if (finished.status === 'failed') {
                throw new Error(finished.error_message || 'OCR/match не удалось.');
            }

            if (finished.status !== 'matched') {
                throw new Error(`Неожиданный статус прогона: ${finished.status}`);
            }

            runProgress.value = '';
        } catch (error) {
            if (disposed || isPollAborted(error)) {
                return;
            }

            runError.value = extractErrorMessage(error);
            runProgress.value = '';
        } finally {
            running.value = false;
        }
    }

    /**
     * @param {string} runId
     * @returns {Promise<SchedulePhotoRun>}
     */
    async function waitForRunCompletion(runId) {
        const deadline = Date.now() + OCR_POLL_MAX_MS;
        const controller = new AbortController();
        pollAbortController = controller;

        if (disposed) {
            controller.abort();
        }

        try {
            while (!disposed && !controller.signal.aborted && Date.now() < deadline) {
                const current = await fetchSchedulePhotoRun(runId, {
                    signal: controller.signal,
                });

                if (disposed || controller.signal.aborted) {
                    throw pollAbortError();
                }

                run.value = current;

                if (current.status === 'matched' || current.status === 'failed' || current.status === 'applied') {
                    return current;
                }

                if (current.status === 'ocr_running') {
                    runProgress.value = 'Распознавание фото (LLM)…';
                } else if (current.status === 'pending') {
                    runProgress.value = 'Ожидание очереди OCR…';
                } else {
                    runProgress.value = `Статус: ${current.status}`;
                }

                await delay(OCR_POLL_INTERVAL_MS, controller.signal);
            }
        } finally {
            if (pollAbortController === controller) {
                pollAbortController = null;
            }
        }

        if (disposed || controller.signal.aborted) {
            throw pollAbortError();
        }

        throw new Error('OCR не завершился вовремя. Проверьте статус позже.');
    }

    async function confirmApply() {
        if (!canApply.value || !run.value) {
            return;
        }

        applying.value = true;
        applyError.value = '';

        try {
            const result = await applySchedulePhotoRun(run.value.id);
            run.value = result;
            applied.value = result.status === 'applied'
                || result.match_result?.applied === true;
        } catch (error) {
            applyError.value = extractErrorMessage(error);
        } finally {
            applying.value = false;
        }
    }

    function cancelMatch() {
        if (applying.value || running.value || resolvingDiscarded.value) {
            return;
        }

        run.value = null;
        runError.value = '';
        applyError.value = '';
        applied.value = false;
        runProgress.value = '';
        resolveDiscardedError.value = '';
        discardedResolveDishName.value = {};
        discardedResolveDates.value = {};
    }

    /**
     * @param {string} rawTitle
     * @param {string} dishName
     */
    function setDiscardedResolveDishName(rawTitle, dishName) {
        discardedResolveDishName.value = {
            ...discardedResolveDishName.value,
            [rawTitle]: dishName,
        };
    }

    /**
     * @param {import('../api/admin/schedulePhoto.js').SchedulePhotoDiscarded} row
     * @returns {string}
     */
    function aliasHintLine(row) {
        if (row.suggested_canon && row.suggested_in_scope) {
            return `По алиасу: ${row.suggested_canon}`;
        }

        if (row.suggested_canon && !row.suggested_in_scope) {
            return `В словаре: ${row.suggested_canon} — нет в выбранных категориях`;
        }

        return 'Алиаса нет — выберите блюдо из каталога; пара OCR→канон сохранится в словарь';
    }

    /**
     * @param {import('../api/admin/schedulePhoto.js').SchedulePhotoDiscarded} row
     * @returns {string}
     */
    function aliasMarkdownSnippet(row) {
        const dish = discardedResolveDishName.value[row.raw_title]
            || row.suggested_canon
            || 'Канон из каталога';

        return `| ${row.raw_title} | ${dish} |`;
    }

    /**
     * Дни с фото внутри окна. Пустой список — OCR даты не сохранил.
     *
     * @param {import('../api/admin/schedulePhoto.js').SchedulePhotoDiscarded} row
     * @returns {string[]}
     */
    function datesOnPhoto(row) {
        const from = windowDateFrom.value;
        const to = windowDateTo.value;

        if (!Array.isArray(row?.dates)) {
            return [];
        }

        return [...new Set(row.dates.filter((date) => (
            typeof date === 'string' && date >= from && date <= to
        )))].sort();
    }

    /**
     * В график идут только дни с фото. Если их нет — отмеченные вручную, не всё окно.
     *
     * @param {import('../api/admin/schedulePhoto.js').SchedulePhotoDiscarded} row
     * @returns {string[]}
     */
    function datesForResolve(row) {
        const photoDates = datesOnPhoto(row);

        if (photoDates.length > 0) {
            return photoDates;
        }

        return discardedResolveDates.value[row.raw_title] ?? [];
    }

    /**
     * @param {string} rawTitle
     * @param {string} date
     * @returns {boolean}
     */
    function isDiscardedDateSelected(rawTitle, date) {
        return (discardedResolveDates.value[rawTitle] ?? []).includes(date);
    }

    /**
     * @param {string} rawTitle
     * @param {string} date
     * @param {boolean} checked
     */
    function toggleDiscardedResolveDate(rawTitle, date, checked) {
        const current = discardedResolveDates.value[rawTitle] ?? [];
        const next = checked
            ? [...new Set([...current, date])].sort()
            : current.filter((item) => item !== date);

        discardedResolveDates.value = {
            ...discardedResolveDates.value,
            [rawTitle]: next,
        };
    }

    /**
     * @param {string} iso
     * @returns {string}
     */
    function formatDateChip(iso) {
        const parts = iso.split('-').map(Number);

        if (parts.length !== 3 || parts.some((n) => !Number.isFinite(n))) {
            return iso;
        }

        const weekdays = ['вс', 'пн', 'вт', 'ср', 'чт', 'пт', 'сб'];
        const date = new Date(parts[0], parts[1] - 1, parts[2]);
        const day = String(parts[2]).padStart(2, '0');
        const month = String(parts[1]).padStart(2, '0');

        return `${day}.${month} ${weekdays[date.getDay()] ?? ''}`;
    }

    /**
     * @param {import('../api/admin/schedulePhoto.js').SchedulePhotoDiscarded} row
     */
    async function resolveDiscardedRow(row) {
        if (!run.value || applied.value || applying.value || resolvingDiscarded.value) {
            return;
        }

        if (run.value.status !== 'matched') {
            return;
        }

        const dates = datesForResolve(row);

        if (dates.length === 0) {
            resolveDiscardedError.value = 'Отметьте день с фото. В график не попадают все дни окна.';

            return;
        }

        const dishName = row.suggested_in_scope && row.suggested_canon
            ? row.suggested_canon
            : (discardedResolveDishName.value[row.raw_title] || '').trim();

        if (!row.suggested_in_scope && dishName === '') {
            resolveDiscardedError.value = `Выберите блюдо для «${row.raw_title}».`;

            return;
        }

        resolvingDiscarded.value = true;
        resolveDiscardedError.value = '';

        try {
            const result = await resolveSchedulePhotoDiscarded(run.value.id, {
                rawTitle: row.raw_title,
                dates,
                dishName: row.suggested_in_scope ? null : dishName,
            });
            run.value = result;

            const nextDishes = { ...discardedResolveDishName.value };
            delete nextDishes[row.raw_title];
            discardedResolveDishName.value = nextDishes;

            const nextDates = { ...discardedResolveDates.value };
            delete nextDates[row.raw_title];
            discardedResolveDates.value = nextDates;
        } catch (error) {
            resolveDiscardedError.value = extractErrorMessage(error);
        } finally {
            resolvingDiscarded.value = false;
        }
    }

    function resetForNewRun() {
        if (running.value || applying.value) {
            return;
        }

        cancelMatch();
        setPhotoFile(null);
        photoError.value = '';
        invalidateReadiness();
    }

    /**
     * @param {string} code
     * @returns {string}
     */
    function issueCodeLabel(code) {
        if (code === 'dish_not_found') {
            return 'Не найдено в каталоге';
        }

        if (code === 'dish_ambiguous') {
            return 'Неоднозначно (>1 кандидат)';
        }

        if (code === 'combo_unresolved') {
            return 'Комбо не разобрано';
        }

        return code || 'Проблема';
    }

    /**
     * Подпись причины отброшенной OCR-строки (не ушла в match).
     *
     * @param {string} reason
     * @returns {string}
     */
    function discardedReasonLabel(reason) {
        if (reason === 'dish_not_found') {
            return 'Нет однозначного канона в scope (0 кандидатов)';
        }

        if (reason === 'dish_ambiguous') {
            return 'Несколько кандидатов в scope — не угадываем';
        }

        if (reason === 'out_of_window') {
            return 'Дата вне окна 7 дней';
        }

        if (reason === 'unspecified' || !reason) {
            return 'Отброшено OCR без однозначного канона';
        }

        return reason;
    }

    /**
     * @param {string[]} dates
     * @returns {string}
     */
    function formatDatesList(dates) {
        if (!Array.isArray(dates) || dates.length === 0) {
            return '—';
        }

        return dates.join(', ');
    }

    return {
        baseUrl,
        baseUrlSelectOptions,
        dateFrom,
        restaurantId,
        selectedCategoryIds,
        photoFile,
        photoPreviewUrl,
        photoError,
        restaurants,
        categories,
        catalogLoading,
        catalogError,
        restaurantsLoading,
        categoriesLoading,
        categoriesError,
        readinessOkMessage,
        readiness,
        readinessLoading,
        readinessError,
        readinessOk,
        running,
        runProgress,
        runError,
        run,
        applying,
        applyError,
        applied,
        restaurantSelectOptions,
        categoryOptions,
        selectedRestaurantName,
        selectedCategoryNames,
        windowDateFrom,
        windowDateTo,
        matchResult,
        matchedCount,
        matchedItems,
        issues,
        issuesNotFound,
        issuesAmbiguous,
        issuesOther,
        discarded,
        catalogScope,
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
    };
}
