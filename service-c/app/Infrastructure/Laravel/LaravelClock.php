<?php

declare(strict_types=1);

namespace App\Infrastructure\Laravel;

use App\Contracts\Shared\ClockInterface;
use DateTimeImmutable;
use Illuminate\Support\Carbon;

/**
 * Laravel-адаптер {@see ClockInterface} поверх часов приложения (с учётом travelTo в тестах).
 */
class LaravelClock implements ClockInterface
{
    /**
     * {@inheritDoc}
     */
    public function now(): DateTimeImmutable
    {
        return Carbon::now()->toDateTimeImmutable();
    }
}
