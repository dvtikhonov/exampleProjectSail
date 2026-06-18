/**
 * Человекочитаемые подписи статусов синхронизации отзывов с Яндекс.Картами.
 * @type {Record<string, string>}
 */
export const SYNC_STATUS_LABELS = {
    pending: 'Ожидает синхронизации',
    syncing: 'Синхронизация…',
    completed: 'Синхронизировано',
    failed: 'Ошибка синхронизации',
};

/**
 * @param {string | null | undefined} status
 * @returns {string}
 */
export function syncStatusLabel(status) {
    return SYNC_STATUS_LABELS[status] ?? status;
}

/**
 * @param {string | null | undefined} status
 * @returns {boolean}
 */
export function isSyncInProgress(status) {
    return status === 'pending' || status === 'syncing';
}

/**
 * @param {string | null | undefined} status
 * @returns {boolean}
 */
export function needsPolling(status) {
    return status === 'pending' || status === 'syncing';
}
