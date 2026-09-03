<?php

declare(strict_types=1);

namespace App\Contracts\Shared;

/**
 * Порт key-value кэша без прямой зависимости от Cache Repository фреймворка.
 */
interface CacheStoreInterface
{
    /**
     * Возвращает значение по ключу или default, если ключ отсутствует.
     */
    public function get(string $key, mixed $default = null): mixed;

    /**
     * Сохраняет значение с TTL в секундах.
     */
    public function put(string $key, mixed $value, int $ttlSeconds): bool;

    /**
     * Сохраняет значение без TTL.
     */
    public function forever(string $key, mixed $value): bool;

    /**
     * Удаляет ключ из кэша.
     */
    public function forget(string $key): bool;
}
