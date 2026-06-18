<?php

declare(strict_types=1);

namespace App\Services\YandexMaps;

use App\Contracts\OrganizationSyncDispatcherInterface;
use App\Jobs\SyncYandexOrganizationReviewsJob;

/**
 * Реализация {@see OrganizationSyncDispatcherInterface} через Laravel Queue.
 */
class QueueOrganizationSyncDispatcher implements OrganizationSyncDispatcherInterface
{
    /** Ставит {@see SyncYandexOrganizationReviewsJob} в очередь. */
    public function dispatch(int $organizationId): void
    {
        SyncYandexOrganizationReviewsJob::dispatch($organizationId);
    }
}
