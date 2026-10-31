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

    /**
     * Сохраняет значение только если ключ ещё отсутствует (set-if-absent).
     *
     * @param  int|null  $ttlSeconds  TTL в секундах; null — поведение хранилища по умолчанию.
     */
    public function add(string $key, mixed $value, ?int $ttlSeconds = null): bool;

    /**
     * Атомарно увеличивает числовое значение по ключу.
     */
    public function increment(string $key, int $by = 1): int;
}
