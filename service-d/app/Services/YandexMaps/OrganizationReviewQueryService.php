<?php

declare(strict_types=1);

namespace App\Services\YandexMaps;

use App\Contracts\OrganizationRepositoryInterface;
use App\Contracts\OrganizationReviewRepositoryInterface;
use App\Exceptions\Organization\OrganizationNotFoundException;
use App\Models\Organization;
use App\Models\OrganizationReview;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class OrganizationReviewQueryService
{
    public function __construct(
        private readonly OrganizationRepositoryInterface $organizationRepository,
        private readonly OrganizationReviewRepositoryInterface $organizationReviewRepository,
    ) {}

    public function findOrganizationForUser(User $user): Organization
    {
        $organization = $this->organizationRepository->findByUserId($user->id);

        if ($organization === null) {
            throw new OrganizationNotFoundException;
        }

        return $organization;
    }

    /**
     * @return LengthAwarePaginator<int, OrganizationReview>
     */
    public function paginatedReviews(Organization $organization): LengthAwarePaginator
    {
        return $this->organizationReviewRepository
            ->paginateByOrganization($organization->id, 50);
    }

    /**
     * @return LengthAwarePaginator<int, OrganizationReview>
     */
    public function paginatedReviewsForUser(User $user): LengthAwarePaginator
    {
        return $this->paginatedReviews($this->findOrganizationForUser($user));
    }
}
