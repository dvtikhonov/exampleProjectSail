<?php

declare(strict_types=1);

namespace App\Contracts\Food\BrisklySync;

/**
 * Краткоживущее хранилище Bearer Briskly (cache, не БД).
 */
interface BrisklySyncTokenStoreInterface
{
    /**
     * Сохраняет токен с TTL.
     */
    public function put(string $sessionId, string $token, int $ttlSeconds): void;

    /**
     * Возвращает токен или null, если истёк/отсутствует.
     */
    public function get(string $sessionId): ?string;

    /**
     * Удаляет токен (после apply / истечения сессии).
     */
    public function forget(string $sessionId): void;
}
