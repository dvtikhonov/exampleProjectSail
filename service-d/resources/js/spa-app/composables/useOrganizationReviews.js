import { ref } from 'vue';
import api from '../api/client';

/** Состояние списка отзывов — singleton refs между вызовами composable. */
const reviews = ref([]);
const pagination = ref(null);
const organizationMeta = ref(null);
const isLoading = ref(false);
const isRefreshing = ref(false);
const warning = ref(null);
const error = ref(null);

function extractErrorMessage(err, fallback = 'Не удалось загрузить отзывы.') {
    const data = err?.response?.data;

    if (data?.message && typeof data.message === 'string') {
        return data.message;
    }

    return fallback;
}

/**
 * Composable для постраничной загрузки отзывов организации.
 *
 * Ответ API: reviews.data + reviews.meta, флаги is_refreshing и warning.
 */
export function useOrganizationReviews() {
    async function fetchReviews(organizationId, page = 1) {
        error.value = null;
        isLoading.value = true;

        try {
            const { data } = await api.get('/organization/reviews', {
                params: {
                    organization_id: organizationId,
                    page,
                },
            });

            reviews.value = data.reviews?.data ?? [];
            pagination.value = data.reviews?.meta ?? null;
            organizationMeta.value = data.organization ?? null;
            isRefreshing.value = Boolean(data.is_refreshing);
            warning.value = data.warning ?? null;

            return data;
        } catch (err) {
            error.value = extractErrorMessage(err);
            reviews.value = [];
            pagination.value = null;
            organizationMeta.value = null;
            isRefreshing.value = false;
            warning.value = null;

            return null;
        } finally {
            isLoading.value = false;
        }
    }

    return {
        reviews,
        pagination,
        organizationMeta,
        isLoading,
        isRefreshing,
        warning,
        error,
        fetchReviews,
    };
}
