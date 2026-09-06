<?php

declare(strict_types=1);

namespace App\Contracts\Shared;

/**
 * Порт записи/чтения метрик профилирования HTTP-запроса (Server-Timing и т.п.).
 *
 * В CLI / без request-контекста реализация — no-op (record) / null (get).
 */
interface RequestTimingRecorderInterface
{
    /**
     * Сохраняет набор метрик под ключом атрибута текущего запроса.
     *
     * @param  array<string, float|int|string>  $timing
     */
    public function record(string $attributeKey, array $timing): void;

    /**
     * Возвращает метрики по ключу атрибута текущего (bound) запроса.
     *
     * @return array<string, float|int|string>|null
     */
    public function get(string $attributeKey): ?array;
}
