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

/**
 * Чтение организации и отзывов для API/UI без обращения к парсеру.
 */
class OrganizationReviewQueryService
{
    /** Сообщение клиенту, когда sync в процессе, но в БД уже есть кеш отзывов. */
    public const REFRESHING_WARNING = 'Отзывы обновляются с Яндекс.Карт. Показаны ранее сохранённые данные.';

    public function __construct(
        private readonly OrganizationRepositoryInterface $organizationRepository,
        private readonly OrganizationReviewRepositoryInterface $organizationReviewRepository,
    ) {}

    /**
     * Организация текущего пользователя: по id или единственная привязанная к user_id.
     *
     * @throws OrganizationNotFoundException
     */
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

    /**
     * @throws OrganizationNotFoundException
     */
    public function findOrganizationById(int $organizationId): Organization
    {
        $organization = $this->organizationRepository->findById($organizationId);

        if ($organization === null) {
            throw new OrganizationNotFoundException;
        }

        return $organization;
    }

    /** true, если sync Pending/Syncing и отзывы уже были сохранены ранее. */
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
