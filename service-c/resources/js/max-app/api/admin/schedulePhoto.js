/**
 * Админ: фото графика → readiness → OCR → match → confirm apply.
 * Токены PhotoText / Cursor — только на сервере.
 */
import { client } from '../http';

const BASE = '/food/admin/schedule-photo';

/**
 * @typedef {object} SchedulePhotoProbePart
 * @property {boolean} ok
 * @property {string} message
 */

/**
 * @typedef {object} SchedulePhotoReadiness
 * @property {boolean} ok
 * @property {SchedulePhotoProbePart} ai_access
 * @property {SchedulePhotoProbePart} phototext
 * @property {SchedulePhotoProbePart} sidecar
 */

/**
 * @typedef {object} SchedulePhotoMatchedItem
 * @property {string} raw_title
 * @property {number} dish_id
 * @property {string} dish_name
 * @property {number} category_id
 * @property {string} category_name
 * @property {string[]} dates
 */

/**
 * @typedef {object} SchedulePhotoIssue
 * @property {string} code
 * @property {string} message
 * @property {string} raw_title
 * @property {string[]} dates
 */

/**
 * @typedef {object} SchedulePhotoDiscarded
 * @property {string} raw_title
 * @property {string} reason
 * @property {string[]} [dates] дни с фото, не всё окно прогона
 * @property {string|null} [suggested_canon]
 * @property {boolean} [suggested_in_scope]
 * @property {boolean} [alias_missing]
 */

/**
 * @typedef {object} SchedulePhotoCatalogScopeDish
 * @property {number} id
 * @property {string} name
 * @property {number} category_id
 * @property {string} category_name
 */

/**
 * @typedef {object} SchedulePhotoMatchResult
 * @property {number} matched_count
 * @property {SchedulePhotoMatchedItem[]} matched
 * @property {SchedulePhotoIssue[]} issues
 * @property {string} date_from
 * @property {string} date_to
 * @property {boolean} applied
 * @property {number[]} categories_applied
 */

/**
 * @typedef {object} SchedulePhotoRun
 * @property {string} id
 * @property {string} status
 * @property {string} base_url
 * @property {string} date_from
 * @property {string} date_to
 * @property {number} restaurant_id
 * @property {number[]} category_ids
 * @property {number} created_by_max_user_id
 * @property {SchedulePhotoMatchResult|null} match_result
 * @property {SchedulePhotoDiscarded[]} discarded
 * @property {SchedulePhotoCatalogScopeDish[]} [catalog_scope]
 * @property {string|null} error_message
 */

/**
 * @typedef {object} SchedulePhotoBaseUrlOption
 * @property {string} value
 * @property {string} label
 */

/**
 * @typedef {object} SchedulePhotoConfig
 * @property {string} default_base_url
 * @property {SchedulePhotoBaseUrlOption[]} allowed_base_urls
 */

/**
 * GET /api/food/admin/schedule-photo/config — allow-list BASE_URL для селекта.
 *
 * @returns {Promise<SchedulePhotoConfig>}
 */
export async function fetchSchedulePhotoConfig() {
    const { data } = await client.get(`${BASE}/config`);

    return data;
}

/**
 * @typedef {object} SchedulePhotoRemoteRestaurant
 * @property {number} id
 * @property {string} name
 */

/**
 * @typedef {object} SchedulePhotoRemoteCategory
 * @property {number} id
 * @property {string} name
 */

/**
 * @typedef {object} SchedulePhotoRemoteCatalog
 * @property {number} restaurant_id
 * @property {string} restaurant_name
 * @property {SchedulePhotoRemoteCategory[]} categories
 */

/**
 * GET /api/food/admin/schedule-photo/restaurants?base_url=
 * Рестораны выбранного origin (токен PhotoText остаётся на сервере).
 *
 * @param {string} baseUrl
 * @returns {Promise<SchedulePhotoRemoteRestaurant[]>}
 */
export async function fetchSchedulePhotoRestaurants(baseUrl) {
    const { data } = await client.get(`${BASE}/restaurants`, {
        params: { base_url: baseUrl },
    });

    return Array.isArray(data?.restaurants) ? data.restaurants : [];
}

/**
 * GET /api/food/admin/schedule-photo/catalog?base_url=&restaurant_id=
 * Категории каталога выбранного origin.
 *
 * @param {string} baseUrl
 * @param {number} restaurantId
 * @returns {Promise<SchedulePhotoRemoteCatalog>}
 */
export async function fetchSchedulePhotoCatalog(baseUrl, restaurantId) {
    const { data } = await client.get(`${BASE}/catalog`, {
        params: {
            base_url: baseUrl,
            restaurant_id: restaurantId,
        },
    });

    return data;
}

/**
 * POST /api/food/admin/schedule-photo/readiness
 * При fail сервер отвечает 422 с тем же JSON (ok:false + пробы) — возвращаем тело, не бросаем.
 *
 * @param {string} baseUrl
 * @returns {Promise<SchedulePhotoReadiness>}
 */
export async function checkSchedulePhotoReadiness(baseUrl) {
    try {
        const { data } = await client.post(`${BASE}/readiness`, {
            base_url: baseUrl,
        });

        return data;
    } catch (error) {
        const data = error?.response?.data;

        if (
            error?.response?.status === 422
            && data
            && typeof data === 'object'
            && typeof data.ok === 'boolean'
            && data.ai_access
            && data.phototext
            && data.sidecar
        ) {
            return /** @type {SchedulePhotoReadiness} */ (data);
        }

        throw error;
    }
}

/**
 * POST /api/food/admin/schedule-photo/runs (multipart)
 *
 * @param {{
 *   baseUrl: string,
 *   dateFrom: string,
 *   restaurantId: number,
 *   categoryIds: number[],
 *   photo: File,
 * }} params
 * @returns {Promise<SchedulePhotoRun>}
 */
export async function startSchedulePhotoRun({
    baseUrl,
    dateFrom,
    restaurantId,
    categoryIds,
    photo,
}) {
    const formData = new FormData();
    formData.append('base_url', baseUrl);
    formData.append('date_from', dateFrom);
    formData.append('restaurant_id', String(restaurantId));

    for (const categoryId of categoryIds) {
        formData.append('category_ids[]', String(categoryId));
    }

    formData.append('photo', photo);

    const { data } = await client.post(`${BASE}/runs`, formData);

    return data;
}

/**
 * GET /api/food/admin/schedule-photo/runs/{runId}
 *
 * @param {string} runId
 * @param {{ signal?: AbortSignal }} [options]
 * @returns {Promise<SchedulePhotoRun>}
 */
export async function fetchSchedulePhotoRun(runId, { signal } = {}) {
    const { data } = await client.get(`${BASE}/runs/${runId}`, { signal });

    return data;
}

/**
 * POST /api/food/admin/schedule-photo/runs/{runId}/apply
 *
 * @param {string} runId
 * @returns {Promise<SchedulePhotoRun>}
 */
export async function applySchedulePhotoRun(runId) {
    const { data } = await client.post(`${BASE}/runs/${runId}/apply`);

    return data;
}

/**
 * POST /api/food/admin/schedule-photo/runs/{runId}/resolve-discarded
 * Канон из алиаса (dish_name пусто) или явный dish_name + даты → rematch.
 *
 * @param {string} runId
 * @param {{
 *   rawTitle: string,
 *   dates: string[],
 *   dishName?: string|null,
 * }} params
 * @returns {Promise<SchedulePhotoRun>}
 */
export async function resolveSchedulePhotoDiscarded(runId, { rawTitle, dates, dishName = null }) {
    /** @type {Record<string, unknown>} */
    const body = {
        raw_title: rawTitle,
        dates,
    };

    if (dishName != null && String(dishName).trim() !== '') {
        body.dish_name = String(dishName).trim();
    }

    const { data } = await client.post(`${BASE}/runs/${runId}/resolve-discarded`, body);

    return data;
}
