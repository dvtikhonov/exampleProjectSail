<?php

declare(strict_types=1);

namespace App\Contracts;

use App\DTO\YandexMaps\ParsedReviewDto;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface OrganizationReviewRepositoryInterface
{
    /**
     * @param  ParsedReviewDto[]  $reviews
     */
    public function replaceForOrganization(int $organizationId, array $reviews): void;

    /**
     * @return LengthAwarePaginator<int, \App\Models\OrganizationReview>
     */
    public function paginateByOrganization(int $organizationId, int $perPage = 50): LengthAwarePaginator;
}
