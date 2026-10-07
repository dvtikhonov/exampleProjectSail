<?php

declare(strict_types=1);

namespace App\Infrastructure\Briskly;

use App\Contracts\Food\BrisklySync\BrisklySyncMatchRunStoreInterface;
use App\Contracts\Shared\CacheStoreInterface;

/**
 * CacheStore-адаптер {@see BrisklySyncMatchRunStoreInterface}.
 */
final class CacheBrisklySyncMatchRunStore implements BrisklySyncMatchRunStoreInterface
{
    private const string KEY_PREFIX = 'briskly_sync:match_generation:';

    public function __construct(
        private readonly CacheStoreInterface $cache,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function allocate(string $sessionId, int $ttlSeconds): string
    {
        $generation = $this->newUuid();
        $this->cache->put($this->key($sessionId), $generation, max(1, $ttlSeconds));

        return $generation;
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
    public function matches(string $sessionId, string $generation): bool
    {
        $current = $this->get($sessionId);

        return $current !== null && hash_equals($current, $generation);
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

    private function newUuid(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        $hex = bin2hex($bytes);

        return sprintf(
            '%s-%s-%s-%s-%s',
            substr($hex, 0, 8),
            substr($hex, 8, 4),
            substr($hex, 12, 4),
            substr($hex, 16, 4),
            substr($hex, 20, 12),
        );
    }
}
