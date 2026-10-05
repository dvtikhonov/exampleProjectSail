<?php

declare(strict_types=1);

namespace App\Contracts\Food\BrisklySync;

use App\DTO\Food\BrisklySync\BrisklySyncVpsNamedItemDto;
use App\DTO\Food\BrisklySync\SourceMenuLineDto;
use App\Exceptions\Food\FoodDomainException;

/**
 * Порт source-каталога VPS для Briskly sync (local DB или remote PhotoText на prod).
 */
interface BrisklySyncVpsCatalogPortInterface
{
    /**
     * Активные рестораны: id и name.
     *
     * @return list<BrisklySyncVpsNamedItemDto>
     *
     * @throws FoodDomainException
     */
    public function listRestaurants(): array;

    /**
     * Категории меню выбранного ресторана: id и name.
     *
     * @return list<BrisklySyncVpsNamedItemDto>
     *
     * @throws FoodDomainException
     */
    public function listVpsCategories(int $restaurantId): array;

    /**
     * Проверяет, что ресторан существует и активен.
     *
     * @throws FoodDomainException
     */
    public function assertRestaurantActive(int $restaurantId): void;

    /**
     * Проверяет, что категория принадлежит ресторану (null — без проверки).
     *
     * @throws FoodDomainException
     */
    public function assertCategoryBelongs(int $restaurantId, ?int $categoryId): void;

    /**
     * Source-линии меню ресторана с фильтрами категории и поиска.
     *
     * @return list<SourceMenuLineDto>
     *
     * @throws FoodDomainException
     */
    public function collectSourceLines(
        int $restaurantId,
        ?int $vpsCategoryId = null,
        ?string $searchText = null,
    ): array;
}
