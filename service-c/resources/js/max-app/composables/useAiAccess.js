import { onScopeDispose, ref } from 'vue';
import {
    extractErrorMessage,
    fetchAiAccessStatus,
    toggleAiAccess as toggleAiAccessRequest,
} from '../api';

/** Периодический опрос статуса (мс) */
const POLL_INTERVAL_MS = 60_000;
/** Небольшой запас после expiresAt для корректной синхронизации (мс) */
const EXPIRES_AT_REFRESH_BUFFER_MS = 1_500;
/** Максимальная задержка таймера в setTimeout (мс) */
const MAX_TIMEOUT_MS = 2_147_483_647;

/**
 * @typedef {import('../api/types.js').AiAccessStatusDto} AiAccessStatusDto
 */

/**
 * @param {AiAccessStatusDto|null|undefined} payload
 * @returns {{ enabled: boolean, activeMaxUserId: number|null, expiresAt: string|null }}
 */
function normalizeStatus(payload) {
    const rawActiveId = payload?.active_max_user_id;
    const activeMaxUserId = Number.isFinite(Number(rawActiveId)) && Number(rawActiveId) > 0
        ? Number(rawActiveId)
        : null;
    const expiresAt = typeof payload?.expires_at === 'string' && payload.expires_at.trim() !== ''
        ? payload.expires_at
        : null;

    return {
        enabled: Boolean(payload?.enabled),
        activeMaxUserId,
        expiresAt,
    };
}

/**
 * @param {string|null} expiresAt
 * @returns {number|null}
 */
function resolveExpiresAtRefreshDelay(expiresAt) {
    if (typeof expiresAt !== 'string' || expiresAt.trim() === '') {
        return null;
    }

    const expiresAtTs = Date.parse(expiresAt);

    if (!Number.isFinite(expiresAtTs)) {
        return null;
    }

    const delay = Math.max(0, expiresAtTs - Date.now() + EXPIRES_AT_REFRESH_BUFFER_MS);

    return Math.min(delay, MAX_TIMEOUT_MS);
}

/**
 * Состояние и действия для кнопки «Вкл. доступ AI» в админ-навигации.
 *
 * @returns {{
 *   enabled: import('vue').Ref<boolean>,
 *   activeMaxUserId: import('vue').Ref<number|null>,
 *   expiresAt: import('vue').Ref<string|null>,
 *   loading: import('vue').Ref<boolean>,
 *   toggleLoading: import('vue').Ref<boolean>,
 *   error: import('vue').Ref<string>,
 *   loadStatus: (options?: { silent?: boolean }) => Promise<void>,
 *   toggleAccess: () => Promise<boolean>,
 *   startPolling: () => void,
 *   stopPolling: () => void,
 * }}
 */
export function useAiAccess() {
    const enabled = ref(false);
    const activeMaxUserId = ref(null);
    const expiresAt = ref(null);
    const loading = ref(false);
    const toggleLoading = ref(false);
    const error = ref('');

    /** @type {ReturnType<typeof setInterval>|null} */
    let pollingTimer = null;
    /** @type {ReturnType<typeof setTimeout>|null} */
    let expiresAtRefreshTimer = null;

    /**
     * Простейшая защита от race-condition: применяем только самый новый ответ.
     * @type {number}
     */
    let loadRequestSeq = 0;

    function clearExpiresAtRefreshTimer() {
        if (expiresAtRefreshTimer !== null) {
            clearTimeout(expiresAtRefreshTimer);
            expiresAtRefreshTimer = null;
        }
    }

    function scheduleExpiresAtRefresh() {
        clearExpiresAtRefreshTimer();

        const delay = resolveExpiresAtRefreshDelay(expiresAt.value);

        if (delay === null) {
            return;
        }

        expiresAtRefreshTimer = setTimeout(() => {
            expiresAtRefreshTimer = null;
            loadStatus({ silent: true });
        }, delay);
    }

    /**
     * @param {AiAccessStatusDto} status
     */
    function applyStatus(status) {
        const normalized = normalizeStatus(status);

        enabled.value = normalized.enabled;
        activeMaxUserId.value = normalized.activeMaxUserId;
        expiresAt.value = normalized.expiresAt;

        scheduleExpiresAtRefresh();
    }

    /**
     * @param {{ silent?: boolean }} [options]
     */
    async function loadStatus({ silent = false } = {}) {
        const requestSeq = ++loadRequestSeq;

        if (!silent) {
            loading.value = true;
            error.value = '';
        }

        try {
            const status = await fetchAiAccessStatus();

            if (requestSeq !== loadRequestSeq) {
                return;
            }

            applyStatus(status);
        } catch (err) {
            if (requestSeq !== loadRequestSeq) {
                return;
            }

            error.value = extractErrorMessage(err);
        } finally {
            if (!silent && requestSeq === loadRequestSeq) {
                loading.value = false;
            }
        }
    }

    /**
     * Переключает доступ AI и синхронизирует локальное состояние.
     *
     * @returns {Promise<boolean>}
     */
    async function toggleAccess() {
        if (toggleLoading.value) {
            return false;
        }

        toggleLoading.value = true;
        error.value = '';

        try {
            const status = await toggleAiAccessRequest();
            applyStatus(status);

            return true;
        } catch (err) {
            error.value = extractErrorMessage(err);

            return false;
        } finally {
            toggleLoading.value = false;
        }
    }

    function startPolling() {
        if (pollingTimer !== null) {
            return;
        }

        pollingTimer = setInterval(() => {
            loadStatus({ silent: true });
        }, POLL_INTERVAL_MS);
    }

    function stopPolling() {
        if (pollingTimer !== null) {
            clearInterval(pollingTimer);
            pollingTimer = null;
        }

        clearExpiresAtRefreshTimer();
    }

    onScopeDispose(() => {
        stopPolling();
    });

    return {
        enabled,
        activeMaxUserId,
        expiresAt,
        loading,
        toggleLoading,
        error,
        loadStatus,
        toggleAccess,
        startPolling,
        stopPolling,
    };
}
