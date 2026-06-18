<?php

declare(strict_types=1);

namespace App\Services\YandexMaps;

use App\Contracts\OrganizationRepositoryInterface;
use App\Contracts\OrganizationSyncDispatcherInterface;
use App\Enums\OrganizationSyncStatus;
use App\Exceptions\Organization\OrganizationNotFoundException;
use App\Models\Organization;

/**
 * Повторная синхронизация отзывов организации с Яндекс.Карт.
 *
 * Сбрасывает статус в Pending и ставит задачу в очередь через {@see OrganizationSyncDispatcherInterface}.
 */
class OrganizationResyncService
{
    public function __construct(
        private readonly OrganizationRepositoryInterface $organizationRepository,
        private readonly OrganizationSyncDispatcherInterface $syncDispatcher,
    ) {}

    /**
     * Запускает resync для существующей организации пользователя.
     *
     * @throws OrganizationNotFoundException
     */
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

        $this->syncDispatcher->dispatch($organization->id);

        return $organization->refresh();
    }
}
