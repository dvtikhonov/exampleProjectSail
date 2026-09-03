<?php

declare(strict_types=1);

namespace App\Services\Food\Menu;

use App\Contracts\Food\Menu\MenuQueryServiceInterface;
use App\Contracts\Shared\CacheStoreInterface;
use App\DTO\Food\Menu\DishDto;
use App\DTO\Food\Menu\MenuCategoryDto;
use App\DTO\Food\Menu\MenuDto;
use App\DTO\Food\Shared\RestaurantSummaryDto;

/**
 * Кэш готовых DTO каталога еды (список ресторанов и меню) с версионированием ключей.
 *
 * Инвалидация — bump версии каталога (см. MenuCatalogCacheInvalidator).
 * TTL — safety-net на случай пропущенной инвалидации.
 */
class CachingMenuQueryService implements MenuQueryServiceInterface
{
    public const string VERSION_KEY = 'food.catalog.version';

    private const int DEFAULT_VERSION = 1;

    private const int DEFAULT_TTL_SECONDS = 600;

    public function __construct(
        private readonly MenuQueryServiceInterface $inner,
        private readonly CacheStoreInterface $cache,
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
        $restaurants = $this->restaurantsFromCachePayload($cached);

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
        $menu = $this->menuFromCachePayload($cached);

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

    /**
     * @return list<RestaurantSummaryDto>|null
     */
    private function restaurantsFromCachePayload(mixed $cached): ?array
    {
        if (! is_array($cached)) {
            return null;
        }

        $restaurants = [];

        foreach ($cached as $item) {
            if (! is_array($item)) {
                return null;
            }

            if (! isset($item['id'], $item['name'], $item['address'])) {
                return null;
            }

            if (! is_int($item['id']) && ! (is_string($item['id']) && ctype_digit($item['id']))) {
                return null;
            }

            if (! is_string($item['name']) || ! is_string($item['address'])) {
                return null;
            }

            $restaurants[] = new RestaurantSummaryDto(
                id: (int) $item['id'],
                name: $item['name'],
                address: $item['address'],
            );
        }

        return $restaurants;
    }

    private function menuFromCachePayload(mixed $cached): ?MenuDto
    {
        if (! is_array($cached)) {
            return null;
        }

        if (! isset($cached['restaurant_id'], $cached['restaurant_name'], $cached['categories'])) {
            return null;
        }

        if (! is_int($cached['restaurant_id']) && ! (is_string($cached['restaurant_id']) && ctype_digit($cached['restaurant_id']))) {
            return null;
        }

        if (! is_string($cached['restaurant_name']) || ! is_array($cached['categories'])) {
            return null;
        }

        $categories = [];

        foreach ($cached['categories'] as $categoryPayload) {
            $category = $this->categoryFromCachePayload($categoryPayload);

            if ($category === null) {
                return null;
            }

            $categories[] = $category;
        }

        return new MenuDto(
            restaurantId: (int) $cached['restaurant_id'],
            restaurantName: $cached['restaurant_name'],
            categories: $categories,
        );
    }

    private function categoryFromCachePayload(mixed $payload): ?MenuCategoryDto
    {
        if (! is_array($payload)) {
            return null;
        }

        if (! isset($payload['id'], $payload['name'], $payload['is_combo_available'], $payload['dishes'])) {
            return null;
        }

        if (! is_int($payload['id']) && ! (is_string($payload['id']) && ctype_digit($payload['id']))) {
            return null;
        }

        if (! is_string($payload['name']) || ! is_bool($payload['is_combo_available']) || ! is_array($payload['dishes'])) {
            return null;
        }

        $dishes = [];

        foreach ($payload['dishes'] as $dishPayload) {
            $dish = $this->dishFromCachePayload($dishPayload);

            if ($dish === null) {
                return null;
            }

            $dishes[] = $dish;
        }

        return new MenuCategoryDto(
            id: (int) $payload['id'],
            name: $payload['name'],
            isComboAvailable: $payload['is_combo_available'],
            dishes: $dishes,
        );
    }

    private function dishFromCachePayload(mixed $payload): ?DishDto
    {
        if (! is_array($payload)) {
            return null;
        }

        if (! isset($payload['id'], $payload['name'], $payload['price'], $payload['is_available'])) {
            return null;
        }

        if (! array_key_exists('image_url', $payload)) {
            return null;
        }

        if (! is_int($payload['id']) && ! (is_string($payload['id']) && ctype_digit($payload['id']))) {
            return null;
        }

        if (! is_string($payload['name']) || ! is_string($payload['price']) || ! is_bool($payload['is_available'])) {
            return null;
        }

        $imageUrl = $payload['image_url'];

        if ($imageUrl !== null && ! is_string($imageUrl)) {
            return null;
        }

        return new DishDto(
            id: (int) $payload['id'],
            name: $payload['name'],
            price: $payload['price'],
            isAvailable: $payload['is_available'],
            imageUrl: $imageUrl,
        );
    }
}
