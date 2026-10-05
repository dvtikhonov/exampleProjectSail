<?php

declare(strict_types=1);

namespace App\Services\Food\BrisklySync;

use App\Contracts\Food\BrisklySync\BrisklySyncSourceCollectorInterface;
use App\Contracts\Food\BrisklySync\BrisklySyncVpsCatalogPortInterface;
use App\Contracts\Food\Menu\MenuCategoryReadRepositoryInterface;
use App\Contracts\Food\Shared\RestaurantRepositoryInterface;
use App\DTO\Food\BrisklySync\BrisklySyncVpsNamedItemDto;
use App\DTO\Food\Menu\MenuCategoryRecord;
use App\DTO\Food\Menu\RestaurantSummaryRecord;
use App\Exceptions\Food\FoodDomainException;

/**
 * Local-адаптер source VPS: репозитории + BrisklySyncSourceCollector (без HTTP).
 */
final class LocalBrisklySyncVpsCatalog implements BrisklySyncVpsCatalogPortInterface
{
    public function __construct(
        private readonly RestaurantRepositoryInterface $restaurants,
        private readonly MenuCategoryReadRepositoryInterface $menuCategories,
        private readonly BrisklySyncSourceCollectorInterface $sourceCollector,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function listRestaurants(): array
    {
        return array_map(
            static fn (RestaurantSummaryRecord $restaurant): BrisklySyncVpsNamedItemDto => new BrisklySyncVpsNamedItemDto(
                id: $restaurant->id,
                name: $restaurant->name,
            ),
            $this->restaurants->findAllActive(),
        );
    }

    /**
     * {@inheritDoc}
     */
    public function listVpsCategories(int $restaurantId): array
    {
        $this->assertRestaurantActive($restaurantId);

        return array_map(
            static fn (MenuCategoryRecord $category): BrisklySyncVpsNamedItemDto => new BrisklySyncVpsNamedItemDto(
                id: $category->id,
                name: $category->name,
            ),
            $this->menuCategories->listForAdmin($restaurantId),
        );
    }

    /**
     * {@inheritDoc}
     */
    public function assertRestaurantActive(int $restaurantId): void
    {
        if ($this->restaurants->findActiveById($restaurantId) === null) {
            throw new FoodDomainException('Ресторан не найден или неактивен.', 422);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function assertCategoryBelongs(int $restaurantId, ?int $categoryId): void
    {
        if ($categoryId === null) {
            return;
        }

        $category = $this->menuCategories->findById($categoryId);

        if ($category === null || $category->restaurantId !== $restaurantId) {
            throw new FoodDomainException('Категория меню не найдена для выбранного ресторана.', 422);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function collectSourceLines(
        int $restaurantId,
        ?int $vpsCategoryId = null,
        ?string $searchText = null,
    ): array {
        return $this->sourceCollector->collectForRestaurant(
            $restaurantId,
            $vpsCategoryId,
            $searchText,
        );
    }
}
