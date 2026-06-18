<?php

declare(strict_types=1);

namespace App\Services\YandexMaps;

use App\Contracts\OrganizationRepositoryInterface;
use App\Contracts\OrganizationReviewRepositoryInterface;
use App\Enums\OrganizationSyncStatus;
use App\Exceptions\Organization\OrganizationNotFoundException;
use App\Models\Organization;
use App\Models\OrganizationReview;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class OrganizationReviewQueryService
{
    public const REFRESHING_WARNING = 'Отзывы обновляются с Яндекс.Карт. Показаны ранее сохранённые данные.';

    public function __construct(
        private readonly OrganizationRepositoryInterface $organizationRepository,
        private readonly OrganizationReviewRepositoryInterface $organizationReviewRepository,
    ) {}

    public function findOrganizationForUser(User $user, ?int $organizationId = null): Organization
    {
        if ($organizationId !== null) {
            $organization = $this->organizationRepository->findById($organizationId);

            if ($organization === null || $organization->user_id !== $user->id) {
                throw new OrganizationNotFoundException;
            }

            return $organization;
        }

        $organization = $this->organizationRepository->findByUserId($user->id);

        if ($organization === null) {
            throw new OrganizationNotFoundException;
        }

        return $organization;
    }

    public function findOrganizationById(int $organizationId): Organization
    {
        $organization = $this->organizationRepository->findById($organizationId);

        if ($organization === null) {
            throw new OrganizationNotFoundException;
        }

        return $organization;
    }

    public function isRefreshingCachedReviews(Organization $organization): bool
    {
        if (! in_array($organization->sync_status, [OrganizationSyncStatus::Pending, OrganizationSyncStatus::Syncing], true)) {
            return false;
        }

        return $this->organizationReviewRepository->countByOrganization($organization->id) > 0;
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
