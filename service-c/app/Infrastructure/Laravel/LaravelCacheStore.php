<?php

declare(strict_types=1);

namespace App\Infrastructure\Laravel;

use App\Contracts\Shared\CacheStoreInterface;
use Illuminate\Contracts\Cache\Repository as CacheRepository;

/**
 * Laravel-адаптер {@see CacheStoreInterface} поверх Cache Repository.
 */
class LaravelCacheStore implements CacheStoreInterface
{
    public function __construct(
        private readonly CacheRepository $cache,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->cache->get($key, $default);
    }

    /**
     * {@inheritDoc}
     */
    public function put(string $key, mixed $value, int $ttlSeconds): bool
    {
        return $this->cache->put($key, $value, $ttlSeconds);
    }

    /**
     * {@inheritDoc}
     */
    public function forever(string $key, mixed $value): bool
    {
        return $this->cache->forever($key, $value);
    }

    /**
     * {@inheritDoc}
     */
    public function forget(string $key): bool
    {
        return $this->cache->forget($key);
    }
}
