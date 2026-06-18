<?php

declare(strict_types=1);

namespace App\Services\YandexMaps;

use App\Contracts\OrganizationRepositoryInterface;
use App\Enums\OrganizationSyncStatus;
use App\Exceptions\Organization\OrganizationNotFoundException;
use App\Jobs\SyncYandexOrganizationReviewsJob;
use App\Models\Organization;

class OrganizationResyncService
{
    public function __construct(
        private readonly OrganizationRepositoryInterface $organizationRepository,
    ) {}

    public function resync(int $organizationId): Organization
    {
        $organization = $this->organizationRepository->findById($organizationId);

        if ($organization === null) {
            throw new OrganizationNotFoundException;
        }

        $this->organizationRepository->updateSyncStatus(
            organizationId: $organization->id,
            status: OrganizationSyncStatus::Pending,
            syncError: null,
        );

        SyncYandexOrganizationReviewsJob::dispatch($organization->id);

        return $organization->refresh();
    }
}
