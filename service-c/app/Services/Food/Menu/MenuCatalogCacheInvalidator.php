<?php

declare(strict_types=1);

namespace App\Services\Food\Menu;

use App\Contracts\Food\Menu\MenuCatalogCacheInvalidatorInterface;
use Illuminate\Contracts\Cache\Repository as CacheRepository;

/**
 * Инвалидация кэша каталога через bump версии (без cache tags).
 *
 * Старые ключи с прежней версией доживают TTL; новые чтения идут в miss.
 */
class MenuCatalogCacheInvalidator implements MenuCatalogCacheInvalidatorInterface
{
    public const string VERSION_CACHE_KEY = 'food.catalog.version';

    private const int DEFAULT_VERSION = 1;

    public function __construct(
        private readonly CacheRepository $cache,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function invalidateAll(): void
    {
        $current = (int) $this->cache->get(self::VERSION_CACHE_KEY, self::DEFAULT_VERSION);

        if ($current < self::DEFAULT_VERSION) {
            $current = self::DEFAULT_VERSION;
        }

        $this->cache->forever(self::VERSION_CACHE_KEY, $current + 1);
    }

    /**
     * {@inheritDoc}
     */
    public function invalidateRestaurant(int $restaurantId): void
    {
        $this->invalidateAll();
    }
}
