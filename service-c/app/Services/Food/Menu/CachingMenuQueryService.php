<?php

declare(strict_types=1);

namespace App\Services\Food\Menu;

use App\Contracts\Food\Menu\MenuQueryServiceInterface;
use App\Contracts\Shared\CacheStoreInterface;
use App\DTO\Food\Menu\MenuDto;
use App\DTO\Food\Shared\RestaurantSummaryDto;

/**
 * Кэш готовых DTO каталога еды (список ресторанов и меню) с версионированием ключей.
 *
 * Инвалидация — bump версии каталога (см. MenuCatalogCacheInvalidator).
 * TTL — safety-net на случай пропущенной инвалидации.
 * Hydration payload → DTO — {@see MenuCachePayloadHydrator}.
 */
class CachingMenuQueryService implements MenuQueryServiceInterface
{
    public const string VERSION_KEY = 'food.catalog.version';

    private const int DEFAULT_VERSION = 1;

    private const int DEFAULT_TTL_SECONDS = 600;

    public function __construct(
        private readonly MenuQueryServiceInterface $inner,
        private readonly CacheStoreInterface $cache,
        private readonly MenuCachePayloadHydrator $hydrator,
        private readonly int $ttlSeconds = self::DEFAULT_TTL_SECONDS,
        private readonly bool $enabled = true,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function listActiveRestaurants(): array
    {
        if (! $this->enabled) {
            return $this->inner->listActiveRestaurants();
        }

        $key = $this->restaurantsCacheKey();
        $cached = $this->cache->get($key);
        $restaurants = $this->hydrator->restaurantsFromCachePayload($cached);

        if ($restaurants !== null) {
            return $restaurants;
        }

        if ($cached !== null) {
            $this->cache->forget($key);
        }

        $restaurants = $this->inner->listActiveRestaurants();
        $this->cache->put(
            $key,
            array_map(
                static fn (RestaurantSummaryDto $restaurant): array => $restaurant->toArray(),
                $restaurants,
            ),
            $this->ttlSeconds,
        );

        return $restaurants;
    }

    /**
     * {@inheritDoc}
     */
    public function getRestaurantMenu(int $restaurantId, bool $includeUnavailable = false): MenuDto
    {
        if (! $this->enabled) {
            return $this->inner->getRestaurantMenu($restaurantId, $includeUnavailable);
        }

        $key = $this->menuCacheKey($restaurantId, $includeUnavailable);
        $cached = $this->cache->get($key);
        $menu = $this->hydrator->menuFromCachePayload($cached);

        if ($menu !== null) {
            return $menu;
        }

        if ($cached !== null) {
            $this->cache->forget($key);
        }

        $menu = $this->inner->getRestaurantMenu($restaurantId, $includeUnavailable);
        $this->cache->put($key, $menu->toArray(), $this->ttlSeconds);

        return $menu;
    }

    /**
     * Ключ списка ресторанов с текущей версией каталога.
     */
    public function restaurantsCacheKey(): string
    {
        return sprintf('food.catalog.v%d.restaurants', $this->catalogVersion());
    }

    /**
     * Ключ меню ресторана с текущей версией каталога.
     */
    public function menuCacheKey(int $restaurantId, bool $includeUnavailable): string
    {
        $suffix = $includeUnavailable ? 'all' : 'pub';

        return sprintf('food.catalog.v%d.menu.%d.%s', $this->catalogVersion(), $restaurantId, $suffix);
    }

    /**
     * Текущая версия каталога (по умолчанию 1).
     */
    public function catalogVersion(): int
    {
        $version = $this->cache->get(self::VERSION_KEY, self::DEFAULT_VERSION);

        if (is_int($version)) {
            return max(self::DEFAULT_VERSION, $version);
        }

        if (is_numeric($version)) {
            return max(self::DEFAULT_VERSION, (int) $version);
        }

        return self::DEFAULT_VERSION;
    }
}
