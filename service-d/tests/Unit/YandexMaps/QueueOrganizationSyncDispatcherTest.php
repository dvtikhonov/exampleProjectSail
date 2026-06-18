<?php

declare(strict_types=1);

namespace Tests\Unit\YandexMaps;

use App\Jobs\SyncYandexOrganizationReviewsJob;
use App\Services\YandexMaps\QueueOrganizationSyncDispatcher;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class QueueOrganizationSyncDispatcherTest extends TestCase
{
    public function test_dispatch_pushes_sync_job_to_bus(): void
    {
        Bus::fake();

        $dispatcher = new QueueOrganizationSyncDispatcher;
        $dispatcher->dispatch(42);

        Bus::assertDispatched(SyncYandexOrganizationReviewsJob::class);
    }
}
