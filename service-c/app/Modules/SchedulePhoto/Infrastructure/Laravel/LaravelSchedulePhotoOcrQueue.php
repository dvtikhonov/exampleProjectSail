<?php

declare(strict_types=1);

namespace App\Modules\SchedulePhoto\Infrastructure\Laravel;

use App\Contracts\Shared\JobDispatcherInterface;
use App\Modules\SchedulePhoto\Application\Port\SchedulePhotoOcrQueueInterface;
use App\Modules\SchedulePhoto\Interface\Jobs\RunSchedulePhotoOcrJob;

/**
 * Laravel-адаптер постановки {@see RunSchedulePhotoOcrJob}.
 */
final class LaravelSchedulePhotoOcrQueue implements SchedulePhotoOcrQueueInterface
{
    public function __construct(
        private readonly JobDispatcherInterface $jobs,
        private readonly int $startTimeoutSeconds,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function dispatch(string $runId): void
    {
        $this->jobs->dispatch(new RunSchedulePhotoOcrJob(
            runId: $runId,
            timeoutSeconds: $this->startTimeoutSeconds,
        ));
    }
}
