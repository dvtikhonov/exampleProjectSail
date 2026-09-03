<?php

declare(strict_types=1);

namespace App\Contracts\Shared;

use DateTimeImmutable;

/**
 * Порт текущего времени без зависимости от Laravel helper now().
 */
interface ClockInterface
{
    /**
     * Возвращает текущий момент времени.
     */
    public function now(): DateTimeImmutable;
}
