<script setup>
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import AuthErrorAlert from '../components/AuthErrorAlert.vue';
import LoadingSpinner from '../components/LoadingSpinner.vue';
import { useOrganization } from '../composables/useOrganization';
import { useOrganizationReviews } from '../composables/useOrganizationReviews';

const POLL_INTERVAL_MS = 4000;

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

const syncStatusLabels = {
    pending: 'Ожидает синхронизации',
    syncing: 'Синхронизация…',
    completed: 'Синхронизировано',
    failed: 'Ошибка синхронизации',
};

const displayError = computed(() => reviewsError.value);

const syncStatus = computed(() => organizationMeta.value?.sync_status ?? null);

const isSyncInProgress = computed(() => syncStatus.value === 'pending' || syncStatus.value === 'syncing');

const metrics = computed(() => organizationMeta.value ?? null);

const refreshWarning = computed(() => {
    return warning.value
        ?? 'Отзывы обновляются с Яндекс.Карт. Показаны ранее сохранённые данные.';
});

function formatRating(value) {
    if (value === null || value === undefined) {
        return '—';
    }

    return Number(value).toFixed(1);
}

function formatCount(value) {
    if (value === null || value === undefined) {
        return '—';
    }

    return value.toLocaleString('ru-RU');
}

function formatDate(iso) {
    if (!iso) {
        return '—';
    }

    return new Date(iso).toLocaleDateString('ru-RU', {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    });
}

function syncStatusLabel(status) {
    return syncStatusLabels[status] ?? status;
}

function needsPolling(status) {
    return status === 'pending' || status === 'syncing';
}

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
</script>

<template>
    <div class="flex min-h-dvh items-start justify-center px-4 py-10">
        <div class="w-full max-w-4xl rounded-2xl border border-slate-200 bg-white p-8 shadow-sm">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="text-sm font-medium uppercase tracking-wide text-sky-700">
                        Отзывы
                    </p>
                    <h1 class="mt-1 text-2xl font-semibold text-slate-900">
                        {{ metrics?.name ?? 'Отзывы организации' }}
                    </h1>
                    <p
                        v-if="metrics?.address"
                        class="mt-1 text-sm text-slate-600"
                    >
                        {{ metrics.address }}
                    </p>
                </div>
                <router-link
                    :to="{ name: 'settings', params: { organizationId: organizationId } }"
                    class="text-sm font-medium text-sky-700 hover:underline"
                >
                    ← Настройки
                </router-link>
            </div>

            <div
                v-if="isInitialLoad"
                class="mt-10 flex justify-center py-8"
            >
                <LoadingSpinner label="Загрузка отзывов…" />
            </div>

            <template v-else>
                <div
                    v-if="metrics"
                    class="mt-8 grid gap-4 sm:grid-cols-3"
                >
                    <div class="rounded-xl border border-slate-200 bg-slate-50 p-4 text-center">
                        <p class="text-xs font-medium uppercase tracking-wide text-slate-500">
                            Средний рейтинг
                        </p>
                        <p class="mt-2 text-3xl font-semibold text-slate-900">
                            {{ formatRating(metrics.average_rating) }}
                        </p>
                    </div>
                    <div class="rounded-xl border border-slate-200 bg-slate-50 p-4 text-center">
                        <p class="text-xs font-medium uppercase tracking-wide text-slate-500">
                            Оценок
                        </p>
                        <p class="mt-2 text-3xl font-semibold text-slate-900">
                            {{ formatCount(metrics.ratings_count) }}
                        </p>
                    </div>
                    <div class="rounded-xl border border-slate-200 bg-slate-50 p-4 text-center">
                        <p class="text-xs font-medium uppercase tracking-wide text-slate-500">
                            Отзывов
                        </p>
                        <p class="mt-2 text-3xl font-semibold text-slate-900">
                            {{ formatCount(metrics.reviews_count) }}
                        </p>
                    </div>
                </div>

                <div
                    v-if="isRefreshing && reviews.length > 0"
                    class="mt-6 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900"
                    role="status"
                >
                    {{ refreshWarning }}
                </div>

                <div
                    class="mt-6 flex flex-wrap items-center gap-3 rounded-lg border px-4 py-3 text-sm"
                    :class="syncStatus === 'failed'
                        ? 'border-red-200 bg-red-50 text-red-800'
                        : isSyncInProgress
                            ? 'border-amber-200 bg-amber-50 text-amber-900'
                            : 'border-slate-200 bg-slate-50 text-slate-700'"
                >
                    <span
                        v-if="isSyncInProgress"
                        class="inline-block h-4 w-4 animate-spin rounded-full border-2 border-amber-400/40 border-t-amber-700"
                        aria-hidden="true"
                    />
                    <span>
                        Статус синхронизации:
                        <span class="font-medium">{{ syncStatusLabel(syncStatus) }}</span>
                    </span>
                    <span
                        v-if="lastSyncedAt && syncStatus === 'completed'"
                        class="text-slate-500"
                    >
                        · обновлено {{ formatDate(lastSyncedAt) }}
                    </span>
                </div>

                <p
                    v-if="syncError && syncStatus === 'failed'"
                    class="mt-2 text-sm text-red-700"
                >
                    {{ syncError }}
                </p>

                <div class="mt-4">
                    <AuthErrorAlert :message="displayError" />
                </div>

                <div
                    v-if="isReviewsLoading && reviews.length === 0"
                    class="mt-10 flex justify-center py-8"
                >
                    <LoadingSpinner label="Загрузка списка отзывов…" />
                </div>

                <div
                    v-else-if="reviews.length === 0"
                    class="mt-10 rounded-xl border border-dashed border-slate-300 bg-slate-50 px-6 py-10 text-center"
                >
                    <p class="text-sm text-slate-600">
                        <template v-if="isSyncInProgress">
                            Отзывы ещё загружаются. Страница обновится автоматически после синхронизации.
                        </template>
                        <template v-else>
                            Отзывов пока нет. Попробуйте обновить данные в настройках.
                        </template>
                    </p>
                </div>

                <template v-else>
                    <div
                        v-if="pagination && pagination.last_page > 1"
                        class="mt-8 flex flex-wrap items-center justify-between gap-4"
                    >
                        <p class="text-sm text-slate-600">
                            Страница {{ pagination.current_page }} из {{ pagination.last_page }}
                            <span class="text-slate-400">
                                · {{ formatCount(pagination.total) }} записей
                            </span>
                        </p>
                        <div class="flex gap-2">
                            <button
                                type="button"
                                :disabled="isReviewsLoading || pagination.current_page <= 1"
                                class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50"
                                @click="goToPage(pagination.current_page - 1)"
                            >
                                Назад
                            </button>
                            <button
                                type="button"
                                :disabled="isReviewsLoading || pagination.current_page >= pagination.last_page"
                                class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50"
                                @click="goToPage(pagination.current_page + 1)"
                            >
                                Вперёд
                            </button>
                        </div>
                    </div>

                    <div
                        class="overflow-x-auto rounded-xl border border-slate-200"
                        :class="pagination && pagination.last_page > 1 ? 'mt-4' : 'mt-8'"
                    >
                        <table class="min-w-full divide-y divide-slate-200 text-sm">
                            <thead class="bg-slate-50">
                                <tr>
                                    <th
                                        scope="col"
                                        class="px-4 py-3 text-left font-medium text-slate-600"
                                    >
                                        Автор
                                    </th>
                                    <th
                                        scope="col"
                                        class="px-4 py-3 text-left font-medium text-slate-600"
                                    >
                                        Дата
                                    </th>
                                    <th
                                        scope="col"
                                        class="px-4 py-3 text-left font-medium text-slate-600"
                                    >
                                        Оценка
                                    </th>
                                    <th
                                        scope="col"
                                        class="px-4 py-3 text-left font-medium text-slate-600"
                                    >
                                        Текст
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 bg-white">
                                <tr
                                    v-for="review in reviews"
                                    :key="review.id"
                                    class="align-top"
                                >
                                    <td class="whitespace-nowrap px-4 py-3 font-medium text-slate-900">
                                        {{ review.author_name }}
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 text-slate-600">
                                        {{ formatDate(review.published_at) }}
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 text-slate-900">
                                        {{ review.rating ?? '—' }}
                                    </td>
                                    <td class="px-4 py-3 text-slate-700">
                                        <span v-if="review.text">{{ review.text }}</span>
                                        <span
                                            v-else
                                            class="italic text-slate-400"
                                        >Без текста</span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div
                        v-if="pagination && pagination.last_page > 1"
                        class="mt-6 flex flex-wrap items-center justify-between gap-4"
                    >
                        <p class="text-sm text-slate-600">
                            Страница {{ pagination.current_page }} из {{ pagination.last_page }}
                            <span class="text-slate-400">
                                · {{ formatCount(pagination.total) }} записей
                            </span>
                        </p>
                        <div class="flex gap-2">
                            <button
                                type="button"
                                :disabled="isReviewsLoading || pagination.current_page <= 1"
                                class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50"
                                @click="goToPage(pagination.current_page - 1)"
                            >
                                Назад
                            </button>
                            <button
                                type="button"
                                :disabled="isReviewsLoading || pagination.current_page >= pagination.last_page"
                                class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50"
                                @click="goToPage(pagination.current_page + 1)"
                            >
                                Вперёд
                            </button>
                        </div>
                    </div>

                    <div
                        v-if="isReviewsLoading && reviews.length > 0"
                        class="mt-4 flex justify-center py-2"
                    >
                        <LoadingSpinner label="Обновление…" />
                    </div>
                </template>
            </template>
        </div>
    </div>
</template>
