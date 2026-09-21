<?php

declare(strict_types=1);

namespace App\Services\Food\Menu;

use App\Contracts\Food\Menu\MenuCatalogCacheInvalidatorInterface;
use App\Contracts\Shared\CacheStoreInterface;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Инвалидация кэша каталога через bump версии (без cache tags).
 *
 * Старые ключи с прежней версией доживают TTL; новые чтения идут в miss.
 * Сбой записи кэша (например Permission denied на file store) логируется
 * с `event=food.catalog_cache_invalidation_failed` и не роняет успешное
 * сохранение блюда/категории (каталог может быть stale до TTL).
 */
class MenuCatalogCacheInvalidator implements MenuCatalogCacheInvalidatorInterface
{
    public const string VERSION_CACHE_KEY = 'food.catalog.version';

    /** Стабильный ключ для grep/алертов при сбое bump версии кэша. */
    public const string FAILURE_EVENT = 'food.catalog_cache_invalidation_failed';

    private const int DEFAULT_VERSION = 1;

    public function __construct(
        private readonly CacheStoreInterface $cache,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function invalidateAll(): void
    {
        try {
            // Seed DEFAULT_VERSION if missing, then atomic INCR (bare INCR on miss would stay at 1 = default).
            $this->cache->add(self::VERSION_CACHE_KEY, self::DEFAULT_VERSION);
            $this->cache->increment(self::VERSION_CACHE_KEY);
        } catch (Throwable $exception) {
            $this->logger->warning('Menu catalog cache invalidation failed.', [
                'event' => self::FAILURE_EVENT,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
                'cache_key' => self::VERSION_CACHE_KEY,
            ]);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function invalidateRestaurant(int $restaurantId): void
    {
        $this->invalidateAll();
    }
}
