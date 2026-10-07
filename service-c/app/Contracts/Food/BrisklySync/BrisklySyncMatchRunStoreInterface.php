<?php

declare(strict_types=1);

namespace App\Contracts\Food\BrisklySync;

/**
 * Cache текущего match_generation сессии (handshake vs stale callback).
 */
interface BrisklySyncMatchRunStoreInterface
{
    /**
     * Генерирует UUID generation, сохраняет с TTL и возвращает его.
     */
    public function allocate(string $sessionId, int $ttlSeconds): string;

    /**
     * Текущий generation сессии или null.
     */
    public function get(string $sessionId): ?string;

    /**
     * Совпадает ли сохранённый generation.
     */
    public function matches(string $sessionId, string $generation): bool;

    /**
     * Сбрасывает generation после complete/fail/expire.
     */
    public function forget(string $sessionId): void;
}
