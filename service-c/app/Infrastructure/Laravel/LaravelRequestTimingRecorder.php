<?php

declare(strict_types=1);

namespace App\Infrastructure\Laravel;

use App\Contracts\Shared\RequestTimingRecorderInterface;
use Illuminate\Contracts\Foundation\Application;

/**
 * Laravel-адаптер {@see RequestTimingRecorderInterface}: пишет в attributes HTTP-request.
 */
class LaravelRequestTimingRecorder implements RequestTimingRecorderInterface
{
    public function __construct(
        private readonly Application $app,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function record(string $attributeKey, array $timing): void
    {
        if (! $this->app->bound('request')) {
            return;
        }

        $this->app->make('request')->attributes->set($attributeKey, $timing);
    }
}
