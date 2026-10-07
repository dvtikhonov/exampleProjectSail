<?php

declare(strict_types=1);

namespace App\Infrastructure\Laravel;

use App\Contracts\Food\BrisklySync\BrisklySyncMatchQueueInterface;
use App\Contracts\Shared\JobDispatcherInterface;
use App\Jobs\Food\ExpireBrisklySyncMatchJob;
use App\Jobs\Food\RunBrisklySyncMatchJob;

/**
 * Laravel-адаптер {@see BrisklySyncMatchQueueInterface}.
 */
final class LaravelBrisklySyncMatchQueue implements BrisklySyncMatchQueueInterface
{
    public function __construct(
        private readonly JobDispatcherInterface $jobs,
        private readonly int $startTimeoutSeconds,
        private readonly int $llmTimeoutSeconds,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function dispatch(string $sessionId): void
    {
        $this->jobs->dispatch(new RunBrisklySyncMatchJob(
            sessionId: $sessionId,
            timeoutSeconds: $this->startTimeoutSeconds,
        ));
    }

    /**
     * {@inheritDoc}
     */
    public function dispatchExpire(string $sessionId, string $matchGeneration): void
    {
        $this->jobs->dispatch(new ExpireBrisklySyncMatchJob(
            sessionId: $sessionId,
            matchGeneration: $matchGeneration,
            uniqueForSeconds: $this->llmTimeoutSeconds + 60,
            delaySeconds: $this->llmTimeoutSeconds,
        ));
    }
}
