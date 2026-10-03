<?php

declare(strict_types=1);

namespace App\Services\Food\BrisklySync;

use App\Contracts\Food\BrisklySync\BrisklySyncTokenStoreInterface;
use App\Contracts\Shared\CacheStoreInterface;

/**
 * Token store Briskly на базе CacheStoreInterface (TTL, без plaintext в БД).
 */
final class CacheBrisklySyncTokenStore implements BrisklySyncTokenStoreInterface
{
    private const string KEY_PREFIX = 'briskly_sync:token:';

    public function __construct(
        private readonly CacheStoreInterface $cache,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function put(string $sessionId, string $token, int $ttlSeconds): void
    {
        $this->cache->put($this->key($sessionId), $token, max(1, $ttlSeconds));
    }

    /**
     * {@inheritDoc}
     */
    public function get(string $sessionId): ?string
    {
        $value = $this->cache->get($this->key($sessionId));

        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * {@inheritDoc}
     */
    public function forget(string $sessionId): void
    {
        $this->cache->forget($this->key($sessionId));
    }

    private function key(string $sessionId): string
    {
        return self::KEY_PREFIX.$sessionId;
    }
}
