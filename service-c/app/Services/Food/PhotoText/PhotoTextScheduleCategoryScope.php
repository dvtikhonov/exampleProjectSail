<?php

declare(strict_types=1);

namespace App\Services\Food\PhotoText;

use App\Contracts\Food\Menu\MenuCategoryReadRepositoryInterface;
use App\DTO\Food\Menu\MenuCategoryRecord;
use App\Exceptions\Food\FoodDomainException;

/**
 * Нормализация и проверка scope категорий PhotoText schedule (ресторан / optional category_ids).
 */
class PhotoTextScheduleCategoryScope
{
    public function __construct(
        private readonly MenuCategoryReadRepositoryInterface $menuCategoryRepository,
    ) {}

    /**
     * @param  list<int>|null  $categoryIds
     * @return list<int>|null
     */
    public function normalizeCategoryIds(?array $categoryIds): ?array
    {
        if ($categoryIds === null || $categoryIds === []) {
            return null;
        }

        $normalized = array_values(array_unique(array_map(
            static fn (mixed $id): int => (int) $id,
            $categoryIds,
        )));
        sort($normalized);

        return $normalized;
    }

    /**
     * @param  list<int>|null  $categoryIds
     *
     * @throws FoodDomainException
     */
    public function assertOptionalCategoriesBelongToRestaurant(?array $categoryIds, int $restaurantId): void
    {
        if ($categoryIds === null) {
            return;
        }

        foreach ($categoryIds as $categoryId) {
            $record = $this->menuCategoryRepository->findById($categoryId);

            if ($record === null || $record->restaurantId !== $restaurantId) {
                throw new FoodDomainException('Категория меню не найдена для выбранного ресторана.', 422);
            }
        }
    }

    /**
     * Категории ресторана как доменные Record.
     *
     * @return list<MenuCategoryRecord>
     */
    public function listCategoryRecords(int $restaurantId): array
    {
        return $this->menuCategoryRepository->listForAdmin($restaurantId);
    }
}
