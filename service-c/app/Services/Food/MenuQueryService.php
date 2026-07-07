<?php

declare(strict_types=1);

namespace App\Services\Food;

use App\Contracts\Food\DishImageUrlResolverInterface;
use App\Contracts\Food\MenuReadRepositoryInterface;
use App\Contracts\Food\RestaurantRepositoryInterface;
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
        private readonly RestaurantRepositoryInterface $restaurantRepository,
        private readonly MenuReadRepositoryInterface $menuReadRepository,
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
        return array_map(
            static fn (Restaurant $restaurant): RestaurantSummaryDto => new RestaurantSummaryDto(
                id: $restaurant->id,
                name: $restaurant->name,
                address: $restaurant->address,
            ),
            $this->restaurantRepository->findAllActive(),
        );
    }

    /**
     * Возвращает меню ресторана с категориями и блюдами.
     *
     * @throws FoodDomainException
     */
    public function getRestaurantMenu(int $restaurantId): MenuDto
    {
        $restaurant = $this->menuReadRepository->findActiveWithMenu($restaurantId);

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

            if ($dishes === []) {
                continue;
            }

            $categories[] = new MenuCategoryDto(
                id: $category->id,
                name: $category->name,
                isComboAvailable: (bool) $category->is_combo_available,
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
