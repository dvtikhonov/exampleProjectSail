<?php

declare(strict_types=1);

namespace App\Services\Food\Menu;

use App\Contracts\Food\Menu\MenuCatalogCacheInvalidatorInterface;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Инвалидация кэша каталога через bump версии (без cache tags).
 *
 * Старые ключи с прежней версией доживают TTL; новые чтения идут в miss.
 * Сбой записи кэша (например Permission denied на file store) логируется
 * и не роняет успешное сохранение блюда/категории.
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
        try {
            $current = (int) $this->cache->get(self::VERSION_CACHE_KEY, self::DEFAULT_VERSION);

            if ($current < self::DEFAULT_VERSION) {
                $current = self::DEFAULT_VERSION;
            }

            $this->cache->forever(self::VERSION_CACHE_KEY, $current + 1);
        } catch (Throwable $exception) {
            Log::warning('Menu catalog cache invalidation failed.', [
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
