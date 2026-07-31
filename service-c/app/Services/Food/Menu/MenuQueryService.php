<?php

declare(strict_types=1);

namespace App\Services\Food\Menu;

use App\Contracts\Food\Menu\DishImageUrlResolverInterface;
use App\Contracts\Food\Shared\MenuReadRepositoryInterface;
use App\Contracts\Food\Shared\RestaurantRepositoryInterface;
use App\DTO\Food\Menu\DishDto;
use App\DTO\Food\Menu\MenuCategoryDto;
use App\DTO\Food\Menu\MenuDto;
use App\DTO\Food\Shared\RestaurantSummaryDto;
use App\Exceptions\Food\FoodDomainException;
use App\Models\Food\Restaurant;
use App\Services\Food\Shared\FoodMoneyFormatter;

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
     * @param  bool  $includeUnavailable  true — включать недоступные блюда (ручной заказ)
     *
     * @throws FoodDomainException
     */
    public function getRestaurantMenu(int $restaurantId, bool $includeUnavailable = false): MenuDto
    {
        $restaurant = $this->menuReadRepository->findActiveWithMenu($restaurantId, $includeUnavailable);

        if ($restaurant === null) {
            throw new FoodDomainException('Ресторан не найден.', 404);
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
