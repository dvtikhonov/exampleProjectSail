import { ref } from 'vue';
import api from '../api/client';

/** Состояние организации — общее для всех вызовов useOrganization (singleton refs). */
const organization = ref(null);
const isLoading = ref(false);
const isResolving = ref(false);
const isConfirming = ref(false);
const error = ref(null);

function resetError() {
    error.value = null;
}

function extractErrorMessage(err, fallback = 'Произошла ошибка. Попробуйте снова.') {
    const data = err?.response?.data;

    if (data?.message && typeof data.message === 'string') {
        return data.message;
    }

    if (data?.errors && typeof data.errors === 'object') {
        const firstField = Object.keys(data.errors)[0];
        const messages = data.errors[firstField];

        if (Array.isArray(messages) && messages.length > 0) {
            return String(messages[0]);
        }
    }

    return fallback;
}

/**
 * Composable организации пользователя (Яндекс.Карты).
 *
 * Цепочка привязки: resolve → (выбор кандидата) → confirm → resync/fetch.
 */
export function useOrganization() {
    /** GET /organization — текущая или по organization_id из маршрута. */
    async function fetchOrganization(organizationId = null) {
        resetError();
        isLoading.value = true;

        try {
            const params = organizationId ? { organization_id: organizationId } : {};
            const { data } = await api.get('/organization', { params });
            organization.value = data.organization ?? null;

            return organization.value;
        } catch (err) {
            error.value = extractErrorMessage(err, 'Не удалось загрузить организацию.');
            return null;
        } finally {
            isLoading.value = false;
        }
    }

    /** POST /organization/resolve — парсинг URL, возвращает кандидатов и session_id. */
    async function resolveOrganization(url) {
        resetError();
        isResolving.value = true;

        try {
            const { data } = await api.post('/organization/resolve', { url });
            return data;
        } catch (err) {
            error.value = extractErrorMessage(err, 'Не удалось найти организацию по ссылке.');
            return null;
        } finally {
            isResolving.value = false;
        }
    }

    /** POST /organization/confirm — сохраняет выбранного кандидата в БД. */
    async function confirmOrganization(sessionId, orgId) {
        resetError();
        isConfirming.value = true;

        try {
            const { data } = await api.post('/organization/confirm', {
                session_id: sessionId,
                org_id: orgId,
            });
            organization.value = data.organization ?? null;
            return organization.value;
        } catch (err) {
            error.value = extractErrorMessage(err, 'Не удалось сохранить организацию.');
            return null;
        } finally {
            isConfirming.value = false;
        }
    }

    /** POST /organization/resync — ставит задачу на повторную загрузку отзывов. */
    async function resyncOrganization(organizationId) {
        resetError();
        isLoading.value = true;

        try {
            const { data } = await api.post('/organization/resync', {
                organization_id: organizationId,
            });
            organization.value = data.organization ?? null;
            return organization.value;
        } catch (err) {
            error.value = extractErrorMessage(err, 'Не удалось запустить обновление отзывов.');
            return null;
        } finally {
            isLoading.value = false;
        }
    }

    /** GET /organization/sync-status — для polling; обновляет organization in-place. */
    async function fetchSyncStatus(organizationId) {
        try {
            const { data } = await api.get('/organization/sync-status', {
                params: { organization_id: organizationId },
            });

            if (organization.value?.id === organizationId && data) {
                organization.value = {
                    ...organization.value,
                    sync_status: data.sync_status,
                    sync_error: data.sync_error ?? null,
                    last_synced_at: data.last_synced_at ?? null,
                };
            }

            return data;
        } catch (err) {
            error.value = extractErrorMessage(err, 'Не удалось получить статус синхронизации.');
            return null;
        }
    }

    return {
        organization,
        isLoading,
        isResolving,
        isConfirming,
        error,
        fetchOrganization,
        resolveOrganization,
        confirmOrganization,
        resyncOrganization,
        fetchSyncStatus,
    };
}
