<?php

declare(strict_types=1);

namespace App\Repositories\Organization;

use App\Contracts\OrganizationReviewRepositoryInterface;
use App\DTO\YandexMaps\ParsedReviewDto;
use App\Models\OrganizationReview;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;

/**
 * Eloquent-реализация доступа к отзывам организаций.
 *
 * Поддерживает полную замену набора отзывов и инкрементальное слияние с сохранением «сирот»
 * (отзывов, которых больше нет в свежей выдаче Яндекса).
 */
class EloquentOrganizationReviewRepository implements OrganizationReviewRepositoryInterface
{
    /**
     * Полностью заменяет отзывы организации: удаляет старые и вставляет новые пачками по 500.
     *
     * Записи без externalId пропускаются; рейтинг ограничивается диапазоном 0–5.
     *
     * @param  ParsedReviewDto[]  $reviews
     */
    public function replaceForOrganization(int $organizationId, array $reviews): void
    {
        OrganizationReview::query()
            ->where('organization_id', $organizationId)
            ->delete();

        $rows = [];

        foreach ($reviews as $index => $review) {
            if (! $review instanceof ParsedReviewDto || $review->externalId === '') {
                continue;
            }

            $rows[] = [
                'organization_id' => $organizationId,
                'external_review_id' => $review->externalId,
                'author_name' => $review->authorName !== '' ? $review->authorName : 'Аноним',
                'published_at' => ($review->publishedAt ?? Carbon::now())->toDateTimeString(),
                'text' => $review->text,
                'rating' => max(0, min(5, $review->rating ?? 0)),
                'sort_order' => $index,
                'created_at' => now()->toDateTimeString(),
                'updated_at' => now()->toDateTimeString(),
            ];
        }

        if ($rows === []) {
            return;
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            OrganizationReview::query()->insert($chunk);
        }
    }

    /**
     * Обновляет или создаёт отзывы из свежей выдачи и сдвигает «сирот» в конец sort_order.
     *
     * Отзывы, чьих external_review_id нет в новом списке, не удаляются — им назначается
     * sort_order после последнего индекса из Яндекса.
     *
     * @param  ParsedReviewDto[]  $reviews
     */
    public function mergeAndReorderForOrganization(int $organizationId, array $reviews): void
    {
        $yandexExternalIds = [];
        $yandexMaxSortOrder = -1;

        foreach ($reviews as $index => $review) {
            if (! $review instanceof ParsedReviewDto || $review->externalId === '') {
                continue;
            }

            $yandexExternalIds[] = $review->externalId;
            $yandexMaxSortOrder = max($yandexMaxSortOrder, $index);

            OrganizationReview::query()->updateOrCreate(
                [
                    'organization_id' => $organizationId,
                    'external_review_id' => $review->externalId,
                ],
                [
                    'author_name' => $review->authorName !== '' ? $review->authorName : 'Аноним',
                    'published_at' => ($review->publishedAt ?? Carbon::now())->toDateTimeString(),
                    'text' => $review->text,
                    'rating' => max(0, min(5, $review->rating ?? 0)),
                    'sort_order' => $index,
                ],
            );
        }

        $orphanSortStart = $yandexMaxSortOrder + 1;

        $orphansQuery = OrganizationReview::query()
            ->where('organization_id', $organizationId)
            ->orderBy('sort_order');

        if ($yandexExternalIds !== []) {
            $orphansQuery->whereNotIn('external_review_id', $yandexExternalIds);
        }

        foreach ($orphansQuery->get() as $offset => $orphan) {
            $orphan->update(['sort_order' => $orphanSortStart + $offset]);
        }
    }

    public function countByOrganization(int $organizationId): int
    {
        return OrganizationReview::query()
            ->where('organization_id', $organizationId)
            ->count();
    }

    /**
     * Возвращает external_review_id первых N отзывов по sort_order — якоря для stop-sync.
     *
     * @return string[]
     */
    public function findSyncStopAnchors(int $organizationId, int $limit = 3): array
    {
        return OrganizationReview::query()
            ->where('organization_id', $organizationId)
            ->orderBy('sort_order')
            ->limit($limit)
            ->pluck('external_review_id')
            ->all();
    }

    /**
     * @return LengthAwarePaginator<int, OrganizationReview>
     */
    public function paginateByOrganization(int $organizationId, int $perPage = 50): LengthAwarePaginator
    {
        return OrganizationReview::query()
            ->where('organization_id', $organizationId)
            ->orderBy('sort_order')
            ->paginate($perPage);
    }
}
