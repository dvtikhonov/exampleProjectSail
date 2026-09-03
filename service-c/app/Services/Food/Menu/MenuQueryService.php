<?php

declare(strict_types=1);

namespace App\Services\Food\Menu;

use App\Contracts\Food\Menu\DishImageUrlResolverInterface;
use App\Contracts\Food\Menu\MenuQueryServiceInterface;
use App\Contracts\Food\Shared\MenuReadRepositoryInterface;
use App\Contracts\Food\Shared\RestaurantRepositoryInterface;
use App\DTO\Food\Menu\DishDto;
use App\DTO\Food\Menu\DishRecord;
use App\DTO\Food\Menu\MenuCategoryDto;
use App\DTO\Food\Menu\MenuDto;
use App\DTO\Food\Menu\RestaurantSummaryRecord;
use App\DTO\Food\Shared\RestaurantSummaryDto;
use App\Exceptions\Food\FoodDomainException;
use App\Services\Food\Shared\FoodMoneyFormatter;

/**
 * Запросы меню и списка активных ресторанов (без кэша).
 */
class MenuQueryService implements MenuQueryServiceInterface
{
    public function __construct(
        private readonly RestaurantRepositoryInterface $restaurantRepository,
        private readonly MenuReadRepositoryInterface $menuReadRepository,
        private readonly FoodMoneyFormatter $moneyFormatter,
        private readonly DishImageUrlResolverInterface $imageUrlResolver,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function listActiveRestaurants(): array
    {
        return array_map(
            static fn (RestaurantSummaryRecord $restaurant): RestaurantSummaryDto => new RestaurantSummaryDto(
                id: $restaurant->id,
                name: $restaurant->name,
                address: $restaurant->address,
            ),
            $this->restaurantRepository->findAllActive(),
        );
    }

    /**
     * {@inheritDoc}
     */
    public function getRestaurantMenu(int $restaurantId, bool $includeUnavailable = false): MenuDto
    {
        $restaurant = $this->menuReadRepository->findActiveWithMenu($restaurantId, $includeUnavailable);

        if ($restaurant === null) {
            throw new FoodDomainException('Ресторан не найден.', 404);
        }

        $categories = [];

        foreach ($restaurant->menuCategories as $category) {
            $dishes = array_map(
                fn (DishRecord $dish): DishDto => new DishDto(
                    id: $dish->id,
                    name: $dish->name,
                    price: $this->moneyFormatter->format($dish->price),
                    isAvailable: $dish->isAvailable,
                    imageUrl: $this->imageUrlResolver->resolvePublicUrl($dish->id, $dish->imageUrl),
                ),
                $category->dishes,
            );

            if ($dishes === []) {
                continue;
            }

            $categories[] = new MenuCategoryDto(
                id: $category->id,
                name: $category->name,
                isComboAvailable: $category->isComboAvailable,
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
