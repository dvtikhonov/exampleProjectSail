/**
 * Форма выгрузки отчётов Food (max_manager): период, ресторан, тип → очередь → .xlsx в чат MAX.
 */
import { computed, ref, watch } from 'vue';
import { exportFoodReport } from '../api/admin/reports';
import { extractErrorMessage, fetchRestaurants } from '../api';

/** Типы отчёта (значения API report_type) */
export const FOOD_REPORT_TYPES = {
    revenue: 'revenue',
    topDishes: 'top_dishes',
};

/** Опции select типа отчёта */
export const FOOD_REPORT_TYPE_OPTIONS = [
    { value: FOOD_REPORT_TYPES.revenue, label: 'Выручка за период' },
    { value: FOOD_REPORT_TYPES.topDishes, label: 'Топ позиций' },
];

/**
 * @param {Date} date
 * @returns {string} Y-m-d
 */
function toIsoDate(date) {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');

    return `${year}-${month}-${day}`;
}

/**
 * @param {number} daysAgo
 * @returns {string}
 */
function isoDaysAgo(daysAgo) {
    const date = new Date();
    date.setHours(12, 0, 0, 0);
    date.setDate(date.getDate() - daysAgo);

    return toIsoDate(date);
}

/**
 * @param {object} [options]
 * @param {import('vue').Ref<number|string|null|undefined>|import('vue').ComputedRef<number|string|null|undefined>} [options.preselectedRestaurantId]
 * — если в flow уже выбран ресторан, подставляем и блокируем select
 */
export function useFoodReport({ preselectedRestaurantId = null } = {}) {
    const dateFrom = ref(isoDaysAgo(30));
    const dateTo = ref(toIsoDate(new Date()));
    /** @type {import('vue').Ref<string>} */
    const restaurantId = ref('');
    /** @type {import('vue').Ref<string>} */
    const reportType = ref(FOOD_REPORT_TYPES.revenue);

    /** @type {import('vue').Ref<{ id: number, name: string }[]>} */
    const restaurants = ref([]);
    const restaurantsLoading = ref(false);
    const restaurantsError = ref('');

    const sending = ref(false);
    const sendError = ref('');
    const sendSuccess = ref('');

    const restaurantLocked = computed(() => {
        const raw = preselectedRestaurantId?.value;

        return raw !== null && raw !== undefined && String(raw).trim() !== '';
    });

    const restaurantSelectOptions = computed(() => [
        { value: '', label: 'Выберите ресторан', disabled: true },
        ...restaurants.value.map((restaurant) => ({
            value: String(restaurant.id),
            label: restaurant.name,
        })),
    ]);

    const reportTypeOptions = FOOD_REPORT_TYPE_OPTIONS;

    const canSend = computed(() => (
        dateFrom.value !== ''
        && dateTo.value !== ''
        && restaurantId.value !== ''
        && reportType.value !== ''
        && !restaurantsLoading.value
    ));

    watch(
        () => preselectedRestaurantId?.value,
        (id) => {
            if (id === null || id === undefined || String(id).trim() === '') {
                return;
            }

            restaurantId.value = String(id);
        },
        { immediate: true },
    );

    async function loadRestaurants() {
        restaurantsLoading.value = true;
        restaurantsError.value = '';

        try {
            restaurants.value = await fetchRestaurants();

            if (
                restaurantId.value !== ''
                && !restaurants.value.some((item) => String(item.id) === restaurantId.value)
            ) {
                if (!restaurantLocked.value) {
                    restaurantId.value = '';
                }
            }
        } catch (error) {
            restaurantsError.value = extractErrorMessage(error);
            restaurants.value = [];
        } finally {
            restaurantsLoading.value = false;
        }
    }

    /**
     * @param {string} value
     */
    function setDateFrom(value) {
        dateFrom.value = typeof value === 'string' ? value : '';
        sendError.value = '';
        sendSuccess.value = '';
    }

    /**
     * @param {string} value
     */
    function setDateTo(value) {
        dateTo.value = typeof value === 'string' ? value : '';
        sendError.value = '';
        sendSuccess.value = '';
    }

    /**
     * @param {string} value
     */
    function setRestaurantId(value) {
        if (restaurantLocked.value) {
            return;
        }

        restaurantId.value = typeof value === 'string' ? value : '';
        sendError.value = '';
        sendSuccess.value = '';
    }

    /**
     * @param {string} value
     */
    function setReportType(value) {
        reportType.value = typeof value === 'string' ? value : FOOD_REPORT_TYPES.revenue;
        sendError.value = '';
        sendSuccess.value = '';
    }

    async function send() {
        if (!canSend.value || sending.value) {
            return;
        }

        sending.value = true;
        sendError.value = '';
        sendSuccess.value = '';

        try {
            const result = await exportFoodReport({
                dateFrom: dateFrom.value,
                dateTo: dateTo.value,
                restaurantId: Number(restaurantId.value),
                reportType: /** @type {'revenue'|'top_dishes'} */ (reportType.value),
            });

            sendSuccess.value = result.queued
                ? 'Отчёт будет отправлен в чат MAX.'
                : result.message;
        } catch (error) {
            sendError.value = error instanceof Error
                ? error.message
                : extractErrorMessage(error);
        } finally {
            sending.value = false;
        }
    }

    return {
        dateFrom,
        dateTo,
        restaurantId,
        reportType,
        restaurants,
        restaurantsLoading,
        restaurantsError,
        restaurantLocked,
        restaurantSelectOptions,
        reportTypeOptions,
        sending,
        sendError,
        sendSuccess,
        canSend,
        loadRestaurants,
        setDateFrom,
        setDateTo,
        setRestaurantId,
        setReportType,
        send,
    };
}
