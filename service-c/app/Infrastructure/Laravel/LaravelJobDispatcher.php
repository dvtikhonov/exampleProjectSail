<?php

declare(strict_types=1);

namespace App\Infrastructure\Laravel;

use App\Contracts\Shared\JobDispatcherInterface;
use Illuminate\Contracts\Bus\Dispatcher;

/**
 * Laravel-адаптер {@see JobDispatcherInterface} поверх Bus Dispatcher.
 */
class LaravelJobDispatcher implements JobDispatcherInterface
{
    public function __construct(
        private readonly Dispatcher $dispatcher,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function dispatch(object $job): void
    {
        $this->dispatcher->dispatch($job);
    }
}
