import { ref } from 'vue';
import api from '../api/client';

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
 */
export function useOrganization() {
    async function fetchOrganization() {
        resetError();
        isLoading.value = true;

        try {
            const { data } = await api.get('/organization');
            organization.value = data.organization ?? null;
            return organization.value;
        } catch (err) {
            error.value = extractErrorMessage(err, 'Не удалось загрузить организацию.');
            return null;
        } finally {
            isLoading.value = false;
        }
    }

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

    async function resyncOrganization() {
        resetError();
        isLoading.value = true;

        try {
            const { data } = await api.post('/organization/resync');
            organization.value = data.organization ?? null;
            return organization.value;
        } catch (err) {
            error.value = extractErrorMessage(err, 'Не удалось запустить обновление отзывов.');
            return null;
        } finally {
            isLoading.value = false;
        }
    }

    async function fetchSyncStatus() {
        try {
            const { data } = await api.get('/organization/sync-status');

            if (organization.value && data) {
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
