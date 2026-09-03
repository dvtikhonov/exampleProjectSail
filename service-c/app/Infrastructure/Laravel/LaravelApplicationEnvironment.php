<?php

declare(strict_types=1);

namespace App\Infrastructure\Laravel;

use App\Contracts\Shared\ApplicationEnvironmentInterface;
use Illuminate\Contracts\Foundation\Application;

/**
 * Laravel-адаптер {@see ApplicationEnvironmentInterface}.
 */
final class LaravelApplicationEnvironment implements ApplicationEnvironmentInterface
{
    public function __construct(
        private readonly Application $app,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function is(array $environments): bool
    {
        return $this->app->environment($environments);
    }
}
