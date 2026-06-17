import { ref } from 'vue';
import api from '../api/client';

const reviews = ref([]);
const pagination = ref(null);
const organizationMeta = ref(null);
const isLoading = ref(false);
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
 */
export function useOrganizationReviews() {
    async function fetchReviews(page = 1) {
        error.value = null;
        isLoading.value = true;

        try {
            const { data } = await api.get('/organization/reviews', {
                params: { page },
            });

            reviews.value = data.reviews?.data ?? [];
            pagination.value = data.reviews?.meta ?? null;
            organizationMeta.value = data.organization ?? null;

            return data;
        } catch (err) {
            error.value = extractErrorMessage(err);
            reviews.value = [];
            pagination.value = null;
            organizationMeta.value = null;

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
        error,
        fetchReviews,
    };
}
