<?php

declare(strict_types=1);

namespace App\Services\Food;

use App\Contracts\Food\DishImageUrlResolverInterface;
use App\DTO\Food\DishDto;
use App\DTO\Food\MenuCategoryDto;
use App\DTO\Food\MenuDto;
use App\DTO\Food\RestaurantSummaryDto;
use App\Exceptions\Food\FoodDomainException;
use App\Models\Restaurant;

/**
 * Запросы меню и списка активных ресторанов.
 */
class MenuQueryService
{
    public function __construct(
        private readonly FoodMoneyFormatter $moneyFormatter,
        private readonly DishImageUrlResolverInterface $imageUrlResolver,
    ) {}

    /**
     * Возвращает список активных ресторанов.
     *
     * @return list<RestaurantSummaryDto>
     */
    public function listActiveRestaurants(): array
    {
        return Restaurant::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->map(static fn (Restaurant $restaurant): RestaurantSummaryDto => new RestaurantSummaryDto(
                id: $restaurant->id,
                name: $restaurant->name,
                address: $restaurant->address,
            ))
            ->all();
    }

    /**
     * Возвращает меню ресторана с категориями и блюдами.
     *
     * @throws FoodDomainException
     */
    public function getRestaurantMenu(int $restaurantId): MenuDto
    {
        $restaurant = Restaurant::query()
            ->where('is_active', true)
            ->with([
                'menuCategories.dishes' => static fn ($query) => $query->orderBy('name'),
            ])
            ->find($restaurantId);

        if ($restaurant === null) {
            throw new FoodDomainException('Restaurant not found.', 404);
        }

        $categories = [];

        foreach ($restaurant->menuCategories as $category) {
            $dishes = [];

            foreach ($category->dishes as $dish) {
                $dishes[] = new DishDto(
                    id: $dish->id,
                    name: $dish->name,
                    price: $this->moneyFormatter->format($dish->price),
                    isAvailable: $dish->is_available,
                    imageUrl: $this->imageUrlResolver->resolvePublicUrl($dish->id, $dish->image_url),
                );
            }

            $categories[] = new MenuCategoryDto(
                id: $category->id,
                name: $category->name,
                dishes: $dishes,
            );
        }

        return new MenuDto(
            restaurantId: $restaurant->id,
            restaurantName: $restaurant->name,
            categories: $categories,
        );
    }
}
