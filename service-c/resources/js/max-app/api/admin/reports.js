/**
 * Админ: выгрузка отчётов Food (max_manager) в .xlsx.
 *
 * JSON `/revenue` и `/top-dishes` UI не вызывает — только `/export`.
 */
import axios from 'axios';
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
 * Скачивание .xlsx: GET /api/food/admin/reports/export.
 *
 * @param {FoodReportExportParams} params
 * @returns {Promise<{ blob: Blob, filename: string }>}
 */
export async function exportFoodReport({
    dateFrom,
    dateTo,
    restaurantId,
    reportType,
    dateAxis = undefined,
    limit = undefined,
}) {
    const query = {
        date_from: dateFrom,
        date_to: dateTo,
        restaurant_id: restaurantId,
        report_type: reportType,
    };

    if (typeof dateAxis === 'string' && dateAxis !== '') {
        query.date_axis = dateAxis;
    }

    if (typeof limit === 'number' && Number.isFinite(limit)) {
        query.limit = limit;
    }

    try {
        const response = await client.get('/food/admin/reports/export', {
            params: query,
            responseType: 'blob',
        });

        const fallbackName = `report_${restaurantId}_${dateFrom}_${dateTo}.xlsx`;

        return {
            blob: response.data,
            filename: parseContentDispositionFilename(response.headers?.['content-disposition'])
                || fallbackName,
        };
    } catch (error) {
        throw await normalizeExportError(error);
    }
}

/**
 * Запускает скачивание Blob в браузере.
 *
 * @param {Blob} blob
 * @param {string} filename
 */
export function triggerBlobDownload(blob, filename) {
    const objectUrl = URL.createObjectURL(blob);
    const link = document.createElement('a');

    link.href = objectUrl;
    link.download = filename;
    link.rel = 'noopener';
    document.body.appendChild(link);
    link.click();
    link.remove();
    URL.revokeObjectURL(objectUrl);
}

/**
 * @param {string|undefined} header
 * @returns {string|null}
 */
function parseContentDispositionFilename(header) {
    if (typeof header !== 'string' || header.trim() === '') {
        return null;
    }

    const utfMatch = /filename\*=UTF-8''([^;]+)/i.exec(header);

    if (utfMatch?.[1]) {
        try {
            return decodeURIComponent(utfMatch[1].trim().replace(/^["']|["']$/g, ''));
        } catch {
            return utfMatch[1].trim().replace(/^["']|["']$/g, '');
        }
    }

    const plainMatch = /filename="?([^";]+)"?/i.exec(header);

    if (plainMatch?.[1]) {
        return plainMatch[1].trim();
    }

    return null;
}

/**
 * При responseType: blob тело ошибки тоже Blob — разбираем JSON message.
 *
 * @param {unknown} error
 * @returns {Promise<Error>}
 */
async function normalizeExportError(error) {
    if (axios.isAxiosError(error) && error.response?.data instanceof Blob) {
        try {
            const text = await error.response.data.text();
            const payload = JSON.parse(text);

            if (payload && typeof payload === 'object') {
                const validation = payload.errors;

                if (validation && typeof validation === 'object') {
                    for (const messages of Object.values(validation)) {
                        if (Array.isArray(messages) && typeof messages[0] === 'string' && messages[0] !== '') {
                            return new Error(messages[0]);
                        }
                    }
                }

                if (typeof payload.message === 'string' && payload.message.trim() !== '') {
                    return new Error(payload.message.trim());
                }
            }
        } catch {
            // fallback ниже
        }
    }

    return new Error(extractErrorMessage(error));
}
