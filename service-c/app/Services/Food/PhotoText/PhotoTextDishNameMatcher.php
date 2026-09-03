<?php

declare(strict_types=1);

namespace App\Services\Food\PhotoText;

use App\Contracts\Food\Menu\DishCatalogRepositoryInterface;
use App\Contracts\Food\PhotoText\PhotoTextDishNameMatcherInterface;
use App\DTO\Food\Menu\DishRecord;
use App\DTO\Food\PhotoText\PhotoTextDishNameMatchResultDto;
use App\Enums\Food\PhotoText\PhotoTextMatchIssueCode;

/**
 * Exact LOWER(name) в ресторане; при category_ids — только блюда этих категорий.
 */
class PhotoTextDishNameMatcher implements PhotoTextDishNameMatcherInterface
{
    public function __construct(
        private readonly DishCatalogRepositoryInterface $dishRepository,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function match(
        string $searchName,
        int $restaurantId,
        ?array $categoryIds = null,
    ): PhotoTextDishNameMatchResultDto {
        $inRestaurant = $this->dishRepository->findByNameCaseInsensitive($searchName, $restaurantId);
        $filtered = $this->filterByCategoryIds($inRestaurant, $categoryIds);

        if (count($filtered) === 1) {
            return PhotoTextDishNameMatchResultDto::success($filtered[0]);
        }

        if (count($filtered) > 1) {
            return PhotoTextDishNameMatchResultDto::failure(
                PhotoTextMatchIssueCode::DishAmbiguous,
                'Найдено несколько блюд: '.$searchName,
            );
        }

        return PhotoTextDishNameMatchResultDto::failure(
            PhotoTextMatchIssueCode::DishNotFound,
            $this->notFoundMessage($searchName, $restaurantId, $categoryIds),
        );
    }

    /**
     * @param  list<DishRecord>  $dishes
     * @param  list<int>|null  $categoryIds
     * @return list<DishRecord>
     */
    private function filterByCategoryIds(array $dishes, ?array $categoryIds): array
    {
        if ($categoryIds === null) {
            return $dishes;
        }

        $allowed = array_fill_keys($categoryIds, true);

        return array_values(array_filter(
            $dishes,
            static fn (DishRecord $dish): bool => isset($allowed[$dish->menuCategoryId]),
        ));
    }

    /**
     * @param  list<int>|null  $categoryIds
     */
    private function notFoundMessage(string $searchName, int $restaurantId, ?array $categoryIds): string
    {
        $message = 'Блюдо не найдено: '.$searchName;
        $inRestaurant = $this->dishRepository->findByNameCaseInsensitive($searchName, $restaurantId);

        if ($categoryIds !== null && $inRestaurant !== []) {
            return 'Блюдо не относится к указанной категории: '.$searchName;
        }

        if ($inRestaurant === []) {
            $anywhere = $this->dishRepository->findByNameCaseInsensitive($searchName);

            if ($anywhere !== []) {
                return 'Блюдо не относится к указанному ресторану: '.$searchName;
            }
        }

        return $message;
    }
}
