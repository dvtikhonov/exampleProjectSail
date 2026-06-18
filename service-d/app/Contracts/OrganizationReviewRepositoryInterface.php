<?php

declare(strict_types=1);

namespace App\Contracts;

use App\DTO\YandexMaps\ParsedReviewDto;
use App\Models\OrganizationReview;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface OrganizationReviewRepositoryInterface
{
    /**
     * @param  ParsedReviewDto[]  $reviews
     */
    public function replaceForOrganization(int $organizationId, array $reviews): void;

    /**
     * @param  ParsedReviewDto[]  $reviews
     */
    public function mergeAndReorderForOrganization(int $organizationId, array $reviews): void;

    /**
     * @return string[] external_review_id values ordered by sort_order (newest first).
     */
    public function findSyncStopAnchors(int $organizationId, int $limit = 3): array;

    public function countByOrganization(int $organizationId): int;

    /**
     * @return LengthAwarePaginator<int, OrganizationReview>
     */
    public function paginateByOrganization(int $organizationId, int $perPage = 50): LengthAwarePaginator;
}
