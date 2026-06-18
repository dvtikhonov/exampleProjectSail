import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { needsPolling } from '../utils/syncStatus';
import { useOrganization } from './useOrganization';
import { useOrganizationReviews } from './useOrganizationReviews';

const POLL_INTERVAL_MS = 4000;

/**
 * Оркестрация страницы отзывов: загрузка, пагинация и polling статуса синхронизации.
 *
 * При завершении sync (completed) автоматически перезагружает текущую страницу отзывов.
 */
export function useReviewsPage() {
    const route = useRoute();
    const router = useRouter();

    const { fetchSyncStatus } = useOrganization();

    const {
        reviews,
        pagination,
        organizationMeta,
        isLoading: isReviewsLoading,
        isRefreshing,
        warning,
        error: reviewsError,
        fetchReviews,
    } = useOrganizationReviews();

    const isInitialLoad = ref(true);
    const syncError = ref(null);
    const lastSyncedAt = ref(null);
    let pollTimer = null;

    const organizationId = computed(() => Number(route.params.organizationId));

    const displayError = computed(() => reviewsError.value);

    const syncStatus = computed(() => organizationMeta.value?.sync_status ?? null);

    const isSyncInProgress = computed(() => needsPolling(syncStatus.value));

    const metrics = computed(() => organizationMeta.value ?? null);

    const refreshWarning = computed(() => {
        return warning.value
            ?? 'Отзывы обновляются с Яндекс.Карт. Показаны ранее сохранённые данные.';
    });

    function stopPolling() {
        if (pollTimer !== null) {
            clearInterval(pollTimer);
            pollTimer = null;
        }
    }

    function startPollingIfNeeded() {
        stopPolling();

        if (needsPolling(syncStatus.value)) {
            pollTimer = setInterval(pollSyncStatus, POLL_INTERVAL_MS);
        }
    }

    async function loadReviews(page = 1) {
        const id = organizationId.value;

        if (!id || Number.isNaN(id)) {
            await router.replace({ name: 'settings' });
            return false;
        }

        await fetchReviews(id, page);

        return true;
    }

    async function pollSyncStatus() {
        const id = organizationId.value;

        if (!id || Number.isNaN(id)) {
            return;
        }

        const previousStatus = syncStatus.value;
        const data = await fetchSyncStatus(id);

        if (!data) {
            return;
        }

        if (organizationMeta.value) {
            organizationMeta.value = {
                ...organizationMeta.value,
                sync_status: data.sync_status,
            };
        }

        syncError.value = data.sync_error ?? null;
        lastSyncedAt.value = data.last_synced_at ?? lastSyncedAt.value;

        if (!needsPolling(data.sync_status)) {
            stopPolling();

            // Свежие отзывы подтягиваем только при переходе в completed.
            if (data.sync_status === 'completed' && previousStatus !== 'completed') {
                const currentPage = pagination.value?.current_page ?? 1;
                await fetchReviews(id, currentPage);
            }
        }
    }

    async function goToPage(page) {
        if (!page || page < 1 || page === pagination.value?.current_page) {
            return;
        }

        if (pagination.value && page > pagination.value.last_page) {
            return;
        }

        await fetchReviews(organizationId.value, page);
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    watch(syncStatus, (status) => {
        if (needsPolling(status)) {
            startPollingIfNeeded();
        } else {
            stopPolling();
        }
    });

    watch(
        () => route.params.organizationId,
        async () => {
            isInitialLoad.value = true;
            syncError.value = null;
            lastSyncedAt.value = null;

            const loaded = await loadReviews(1);

            if (loaded) {
                startPollingIfNeeded();
            }

            isInitialLoad.value = false;
        },
    );

    onMounted(async () => {
        const loaded = await loadReviews(1);

        if (loaded) {
            startPollingIfNeeded();
        }

        isInitialLoad.value = false;
    });

    onUnmounted(() => {
        stopPolling();
    });

    return {
        organizationId,
        metrics,
        reviews,
        pagination,
        isInitialLoad,
        isReviewsLoading,
        isRefreshing,
        isSyncInProgress,
        displayError,
        refreshWarning,
        syncStatus,
        syncError,
        lastSyncedAt,
        goToPage,
    };
}
