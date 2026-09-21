<?php

declare(strict_types=1);

namespace App\Services\Food\Menu;

use App\DTO\Food\Menu\DishDto;
use App\DTO\Food\Menu\MenuCategoryDto;
use App\DTO\Food\Menu\MenuDto;
use App\DTO\Food\Shared\RestaurantSummaryDto;

/**
 * Восстановление DTO каталога из payload кэша (без ключей / TTL / version).
 *
 * Внутренний collaborator {@see CachingMenuQueryService}; не инжектить из Delivery.
 */
final class MenuCachePayloadHydrator
{
    /**
     * @return list<RestaurantSummaryDto>|null
     */
    public function restaurantsFromCachePayload(mixed $cached): ?array
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

    public function menuFromCachePayload(mixed $cached): ?MenuDto
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
