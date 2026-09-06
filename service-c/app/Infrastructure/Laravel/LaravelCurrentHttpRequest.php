<?php

declare(strict_types=1);

namespace App\Infrastructure\Laravel;

use App\Contracts\Shared\CurrentHttpRequestInterface;
use Illuminate\Contracts\Foundation\Application;

/**
 * Laravel-адаптер {@see CurrentHttpRequestInterface}: читает user() из контейнерного request.
 */
class LaravelCurrentHttpRequest implements CurrentHttpRequestInterface
{
    public function __construct(
        private readonly Application $app,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function user(): mixed
    {
        return $this->app->make('request')->user();
    }
}
