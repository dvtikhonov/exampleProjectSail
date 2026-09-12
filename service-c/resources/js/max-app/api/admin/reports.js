/**
 * Админ: выгрузка отчётов Food (max_manager) — .xlsx в чат MAX.
 *
 * JSON `/revenue` и `/top-dishes` UI не вызывает — только `/export`.
 */
import { client, extractErrorMessage } from '../http';

/**
 * @typedef {'revenue'|'top_dishes'} FoodReportType
 */

/**
 * @typedef {object} FoodReportExportParams
 * @property {string} dateFrom — Y-m-d
 * @property {string} dateTo — Y-m-d
 * @property {number} restaurantId
 * @property {FoodReportType} reportType
 * @property {string} [dateAxis]
 * @property {number} [limit]
 */

/**
 * @typedef {object} FoodReportExportResult
 * @property {boolean} ok
 * @property {string} filename
 * @property {string} message
 */

/**
 * Генерация .xlsx и отправка в чат MAX текущего менеджера:
 * POST /api/food/admin/reports/export.
 *
 * @param {FoodReportExportParams} params
 * @returns {Promise<FoodReportExportResult>}
 */
export async function exportFoodReport({
    dateFrom,
    dateTo,
    restaurantId,
    reportType,
    dateAxis = undefined,
    limit = undefined,
}) {
    const payload = {
        date_from: dateFrom,
        date_to: dateTo,
        restaurant_id: restaurantId,
        report_type: reportType,
    };

    if (typeof dateAxis === 'string' && dateAxis !== '') {
        payload.date_axis = dateAxis;
    }

    if (typeof limit === 'number' && Number.isFinite(limit)) {
        payload.limit = limit;
    }

    try {
        const response = await client.post('/food/admin/reports/export', payload);
        const data = response.data;

        if (!data || typeof data !== 'object' || data.ok !== true) {
            throw new Error(
                typeof data?.message === 'string' && data.message.trim() !== ''
                    ? data.message.trim()
                    : 'Не удалось отправить отчёт в MAX.',
            );
        }

        return {
            ok: true,
            filename: typeof data.filename === 'string' ? data.filename : '',
            message: typeof data.message === 'string' && data.message.trim() !== ''
                ? data.message.trim()
                : 'Отчёт отправлен в чат MAX.',
        };
    } catch (error) {
        throw new Error(extractErrorMessage(error));
    }
}
