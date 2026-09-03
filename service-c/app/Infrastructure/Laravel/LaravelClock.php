<?php

declare(strict_types=1);

namespace App\Infrastructure\Laravel;

use App\Contracts\Shared\ClockInterface;
use DateTimeImmutable;

/**
 * Laravel-адаптер {@see ClockInterface} поверх системных часов.
 */
class LaravelClock implements ClockInterface
{
    /**
     * {@inheritDoc}
     */
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('now');
    }
}
