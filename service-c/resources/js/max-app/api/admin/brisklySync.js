/**
 * Админ: синхронизация цен/позиций с Briskly (роль max_manager).
 * Поиск → results → approvals → apply (UPDATE / CREATE).
 */
import { client, extractErrorMessage } from '../http';

const BASE = '/food/admin/briskly-sync';

/**
 * @typedef {object} BrisklySyncSessionMeta
 * @property {string} id
 * @property {number} restaurant_id
 * @property {number|null} vps_category_id
 * @property {string|null} search_text
 * @property {string|null} clarification
 * @property {string} status
 * @property {boolean} has_snapshot
 * @property {boolean} has_proposals
 * @property {string|null} source_price_hash
 */

/**
 * @typedef {object} BrisklySyncPriceDiffItem
 * @property {string} line_key
 * @property {string} display_name
 * @property {number} briskly_item_id
 * @property {string} briskly_display_name
 * @property {string} source_price
 * @property {string} briskly_price
 */

/**
 * @typedef {object} BrisklySyncCreateItem
 * @property {string} line_key
 * @property {string} display_name
 * @property {string} source_price
 */

/**
 * @typedef {object} BrisklySyncResultsSection
 * @property {object[]} items
 * @property {number} total
 * @property {number} shown
 * @property {boolean} truncated
 */

/**
 * @typedef {object} BrisklySyncResults
 * @property {BrisklySyncResultsSection & { items: BrisklySyncPriceDiffItem[] }} price_updates
 * @property {BrisklySyncResultsSection & { items: BrisklySyncCreateItem[] }} creates
 * @property {{ skipped_briskly_only: number, ambiguous: number, equal_price: number }} counts
 */

/**
 * @typedef {object} BrisklyCategory
 * @property {number} id
 * @property {string} name
 * @property {number|null} [catalog_id]
 * @property {number|null} [parent_id]
 */

/**
 * @typedef {object} BrisklySyncPriceUpdateApproval
 * @property {string} line_key
 * @property {boolean} apply
 * @property {boolean} [confirm_large_delta]
 */

/**
 * @typedef {object} BrisklySyncCreateApproval
 * @property {string} line_key
 * @property {boolean} apply
 * @property {number|null} [briskly_category_id]
 */

/**
 * @typedef {object} BrisklySyncApprovalsPayload
 * @property {BrisklySyncPriceUpdateApproval[]} price_updates
 * @property {BrisklySyncCreateApproval[]} creates
 */

/**
 * @typedef {object} BrisklySyncApplyReport
 * @property {number} updated
 * @property {number} created
 * @property {number} skipped_unchecked
 * @property {number} skipped_equal
 * @property {Array<{ line_key: string, message: string }>} errors
 */

/**
 * @typedef {object} BrisklySyncVpsNamedItem
 * @property {number} id
 * @property {string} name
 */

/**
 * @typedef {object} CreateBrisklySyncSessionParams
 * @property {number} restaurantId
 * @property {number|null} [vpsCategoryId]
 * @property {string|null} [searchText]
 * @property {string|null} [clarification]
 */

/**
 * GET /restaurants — активные рестораны source-каталога (local или remote VPS).
 *
 * @returns {Promise<BrisklySyncVpsNamedItem[]>}
 */
export async function fetchBrisklySyncRestaurants() {
    try {
        const { data } = await client.get(`${BASE}/restaurants`);
        const rows = Array.isArray(data?.restaurants) ? data.restaurants : [];

        return rows.map((row) => ({
            id: Number(row?.id),
            name: String(row?.name ?? ''),
        })).filter((row) => Number.isFinite(row.id) && row.id >= 1);
    } catch (error) {
        throw new Error(extractErrorMessage(error));
    }
}

/**
 * GET /vps-categories — категории меню ресторана из source-каталога.
 *
 * @param {number} restaurantId
 * @returns {Promise<BrisklySyncVpsNamedItem[]>}
 */
export async function fetchBrisklySyncVpsCategories(restaurantId) {
    try {
        const { data } = await client.get(`${BASE}/vps-categories`, {
            params: { restaurant_id: restaurantId },
        });
        const rows = Array.isArray(data?.categories) ? data.categories : [];

        return rows.map((row) => ({
            id: Number(row?.id),
            name: String(row?.name ?? ''),
        })).filter((row) => Number.isFinite(row.id) && row.id >= 1);
    } catch (error) {
        throw new Error(extractErrorMessage(error));
    }
}

/**
 * POST /sessions — создать сессию (токен захватывается на сервере, в ответе нет).
 *
 * @param {CreateBrisklySyncSessionParams} params
 * @returns {Promise<BrisklySyncSessionMeta>}
 */
export async function createBrisklySyncSession({
    restaurantId,
    vpsCategoryId = null,
    searchText = null,
    clarification = null,
}) {
    const payload = {
        restaurant_id: restaurantId,
    };

    if (vpsCategoryId !== null && vpsCategoryId !== undefined) {
        payload.vps_category_id = vpsCategoryId;
    }

    if (typeof searchText === 'string' && searchText.trim() !== '') {
        payload.search_text = searchText.trim();
    }

    if (typeof clarification === 'string' && clarification.trim() !== '') {
        payload.clarification = clarification.trim();
    }

    try {
        const { data } = await client.post(`${BASE}/sessions`, payload);

        return data.session;
    } catch (error) {
        throw new Error(extractErrorMessage(error));
    }
}

/**
 * GET /sessions/{id}
 *
 * @param {string} sessionId
 * @returns {Promise<BrisklySyncSessionMeta>}
 */
export async function fetchBrisklySyncSession(sessionId) {
    try {
        const { data } = await client.get(`${BASE}/sessions/${sessionId}`);

        return data.session;
    } catch (error) {
        throw new Error(extractErrorMessage(error));
    }
}

/**
 * POST /sessions/{id}/snapshot
 *
 * @param {string} sessionId
 * @returns {Promise<{ session: BrisklySyncSessionMeta, snapshotCount: number }>}
 */
export async function loadBrisklySyncSnapshot(sessionId) {
    try {
        const { data } = await client.post(`${BASE}/sessions/${sessionId}/snapshot`);

        return {
            session: data.session,
            snapshotCount: typeof data.snapshot_count === 'number' ? data.snapshot_count : 0,
        };
    } catch (error) {
        throw new Error(extractErrorMessage(error));
    }
}

/**
 * POST /sessions/{id}/match — 202, status matching; UI поллит GET /sessions/{id}.
 *
 * @param {string} sessionId
 * @param {{ rematch?: boolean }} [options]
 * @returns {Promise<BrisklySyncSessionMeta>}
 */
export async function matchBrisklySyncSession(sessionId, { rematch = false } = {}) {
    try {
        const { data } = await client.post(`${BASE}/sessions/${sessionId}/match`, {
            rematch: rematch === true,
        });

        return data.session;
    } catch (error) {
        throw new Error(extractErrorMessage(error));
    }
}

/**
 * GET /sessions/{id}/sync-results — ≤25+25, без token.
 *
 * @param {string} sessionId
 * @returns {Promise<BrisklySyncResults>}
 */
export async function fetchBrisklySyncResults(sessionId) {
    try {
        const { data } = await client.get(`${BASE}/sessions/${sessionId}/sync-results`);

        return {
            price_updates: normalizeResultsSection(data?.price_updates),
            creates: normalizeResultsSection(data?.creates),
            counts: {
                skipped_briskly_only: Number(data?.counts?.skipped_briskly_only ?? 0),
                ambiguous: Number(data?.counts?.ambiguous ?? 0),
                equal_price: Number(data?.counts?.equal_price ?? 0),
            },
        };
    } catch (error) {
        throw new Error(extractErrorMessage(error));
    }
}

/**
 * GET /sessions/{id}/briskly-categories — для select CREATE.
 *
 * @param {string} sessionId
 * @returns {Promise<BrisklyCategory[]>}
 */
export async function fetchBrisklyCategories(sessionId) {
    try {
        const { data } = await client.get(`${BASE}/sessions/${sessionId}/briskly-categories`);

        return Array.isArray(data?.categories) ? data.categories : [];
    } catch (error) {
        throw new Error(extractErrorMessage(error));
    }
}

/**
 * PUT /sessions/{id}/approvals — галочки (без клиентского price).
 *
 * @param {string} sessionId
 * @param {BrisklySyncApprovalsPayload} approvals
 * @returns {Promise<BrisklySyncSessionMeta>}
 */
export async function updateBrisklySyncApprovals(sessionId, approvals) {
    const payload = {
        price_updates: Array.isArray(approvals?.price_updates) ? approvals.price_updates : [],
        creates: Array.isArray(approvals?.creates) ? approvals.creates : [],
    };

    try {
        const { data } = await client.put(`${BASE}/sessions/${sessionId}/approvals`, payload);

        return data.session;
    } catch (error) {
        throw new Error(extractErrorMessage(error));
    }
}

/**
 * POST /sessions/{id}/apply — запись в Briskly по сохранённым approvals.
 *
 * @param {string} sessionId
 * @returns {Promise<BrisklySyncApplyReport>}
 */
export async function applyBrisklySyncSession(sessionId) {
    try {
        const { data } = await client.post(`${BASE}/sessions/${sessionId}/apply`);

        return normalizeApplyReport(data?.report);
    } catch (error) {
        throw new Error(extractErrorMessage(error));
    }
}

/**
 * @param {unknown} raw
 * @returns {BrisklySyncResultsSection}
 */
function normalizeResultsSection(raw) {
    const section = raw && typeof raw === 'object' ? raw : {};
    const items = Array.isArray(section.items) ? section.items : [];

    return {
        items,
        total: Number(section.total ?? items.length),
        shown: Number(section.shown ?? items.length),
        truncated: section.truncated === true,
    };
}

/**
 * @param {unknown} raw
 * @returns {BrisklySyncApplyReport}
 */
function normalizeApplyReport(raw) {
    const report = raw && typeof raw === 'object' ? raw : {};
    const errorsRaw = Array.isArray(report.errors) ? report.errors : [];

    return {
        updated: Number(report.updated ?? 0),
        created: Number(report.created ?? 0),
        skipped_unchecked: Number(report.skipped_unchecked ?? 0),
        skipped_equal: Number(report.skipped_equal ?? 0),
        errors: errorsRaw.map((error) => ({
            line_key: String(error?.line_key ?? ''),
            message: String(error?.message ?? ''),
        })),
    };
}
