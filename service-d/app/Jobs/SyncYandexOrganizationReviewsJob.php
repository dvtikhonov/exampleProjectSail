<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Contracts\OrganizationRepositoryInterface;
use App\Enums\OrganizationSyncStatus;
use App\Services\YandexMaps\OrganizationSyncService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class SyncYandexOrganizationReviewsJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 900;

    public function __construct(
        private readonly int $organizationId,
    ) {}

    public function handle(OrganizationSyncService $syncService): void
    {
        $syncService->sync($this->organizationId);
    }

    public function failed(?Throwable $exception, OrganizationRepositoryInterface $organizationRepository): void
    {
        $message = $exception?->getMessage();

        if ($message === null || $message === '') {
            $message = 'Не удалось синхронизировать отзывы.';
        }

        $organizationRepository->updateSyncStatus(
            organizationId: $this->organizationId,
            status: OrganizationSyncStatus::Failed,
            syncError: $message,
        );
    }
}
