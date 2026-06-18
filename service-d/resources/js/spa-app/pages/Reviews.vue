<script setup>
/**
 * Страница отзывов организации с Яндекс.Карт.
 *
 * Данные и побочные эффекты — в composable useReviewsPage:
 * - метрики организации (рейтинг, количество отзывов);
 * - пагинированный список отзывов;
 * - polling статуса синхронизации с Яндекс.Картами;
 * - предупреждение при фоновом обновлении списка.
 *
 * organizationId берётся из маршрута /reviews/:organizationId.
 */
import AuthErrorAlert from '../components/AuthErrorAlert.vue';
import LoadingSpinner from '../components/LoadingSpinner.vue';
import PageHeader from '../components/layout/PageHeader.vue';
import PageShell from '../components/layout/PageShell.vue';
import ReviewsEmptyState from '../components/reviews/ReviewsEmptyState.vue';
import ReviewsMetrics from '../components/reviews/ReviewsMetrics.vue';
import ReviewsPagination from '../components/reviews/ReviewsPagination.vue';
import ReviewsRefreshWarning from '../components/reviews/ReviewsRefreshWarning.vue';
import ReviewsTable from '../components/reviews/ReviewsTable.vue';
import SyncStatusBanner from '../components/reviews/SyncStatusBanner.vue';
import { useReviewsPage } from '../composables/useReviewsPage';

const {
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
} = useReviewsPage();
</script>

<template>
    <PageShell max-width="4xl">
        <!-- Шапка: название и адрес организации, ссылка назад в настройки -->
        <PageHeader
            eyebrow="Отзывы"
            :title="metrics?.name ?? 'Отзывы организации'"
            :subtitle="metrics?.address"
            :back-link="{
                name: 'settings',
                params: { organizationId },
                label: '← Настройки',
            }"
        />

        <!-- Первичная загрузка метрик и первой страницы отзывов -->
        <div
            v-if="isInitialLoad"
            class="mt-10 flex justify-center py-8"
        >
            <LoadingSpinner label="Загрузка отзывов…" />
        </div>

        <template v-else>
            <!-- Сводка: рейтинг, количество отзывов, дата последней синхронизации -->
            <ReviewsMetrics
                v-if="metrics"
                :metrics="metrics"
            />

            <!-- Фоновое обновление: показываем сохранённые данные + предупреждение -->
            <ReviewsRefreshWarning
                v-if="isRefreshing && reviews.length > 0"
                :message="refreshWarning"
            />

            <!-- Баннер статуса синхронизации (idle / in_progress / failed) -->
            <SyncStatusBanner
                :status="syncStatus"
                :last-synced-at="lastSyncedAt"
                :sync-error="syncError"
            />

            <div class="mt-4">
                <AuthErrorAlert :message="displayError" />
            </div>

            <!-- Список ещё не загружен -->
            <div
                v-if="isReviewsLoading && reviews.length === 0"
                class="mt-10 flex justify-center py-8"
            >
                <LoadingSpinner label="Загрузка списка отзывов…" />
            </div>

            <!-- Нет отзывов: пустое состояние или ожидание первой синхронизации -->
            <ReviewsEmptyState
                v-else-if="reviews.length === 0"
                :is-sync-in-progress="isSyncInProgress"
            />

            <template v-else>
                <!-- Пагинация сверху и снизу таблицы -->
                <ReviewsPagination
                    v-if="pagination && pagination.last_page > 1"
                    position="top"
                    :pagination="pagination"
                    :loading="isReviewsLoading"
                    @page-change="goToPage"
                />

                <ReviewsTable
                    :reviews="reviews"
                    :pagination="pagination"
                />

                <ReviewsPagination
                    v-if="pagination && pagination.last_page > 1"
                    position="bottom"
                    :pagination="pagination"
                    :loading="isReviewsLoading"
                    @page-change="goToPage"
                />

                <!-- Смена страницы при уже отображённых отзывах -->
                <div
                    v-if="isReviewsLoading && reviews.length > 0"
                    class="mt-4 flex justify-center py-2"
                >
                    <LoadingSpinner label="Обновление…" />
                </div>
            </template>
        </template>
    </PageShell>
</template>
