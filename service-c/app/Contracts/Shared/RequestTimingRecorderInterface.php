<?php

declare(strict_types=1);

namespace App\Contracts\Shared;

/**
 * Порт записи метрик профилирования HTTP-запроса (Server-Timing и т.п.).
 *
 * В CLI / без request-контекста реализация — no-op.
 */
interface RequestTimingRecorderInterface
{
    /**
     * Сохраняет набор метрик под ключом атрибута текущего запроса.
     *
     * @param  array<string, float|int|string>  $timing
     */
    public function record(string $attributeKey, array $timing): void;
}
