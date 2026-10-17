<?php

declare(strict_types=1);

namespace App\Modules\SchedulePhoto\Services;

use App\Exceptions\Food\FoodDomainException;
use App\Modules\SchedulePhoto\Contracts\SchedulePhotoCatalogScopeBuilderInterface;
use App\Modules\SchedulePhoto\Contracts\SchedulePhotoPhotoTextClientInterface;

/**
 * Собирает список блюд scope из каталога PhotoText на выбранном BASE_URL.
 */
final class SchedulePhotoCatalogScopeBuilder implements SchedulePhotoCatalogScopeBuilderInterface
{
    public function __construct(
        private readonly SchedulePhotoPhotoTextClientInterface $photoText,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function build(string $baseUrl, int $restaurantId, array $categoryIds): array
    {
        if ($categoryIds === []) {
            throw new FoodDomainException('Нужна хотя бы одна категория для OCR графика.', 422);
        }

        $wanted = [];
        foreach ($categoryIds as $id) {
            $wanted[(int) $id] = true;
        }

        $catalog = $this->photoText->getCatalog($baseUrl, $restaurantId);
        if ($catalog->restaurantId !== $restaurantId) {
            throw new FoodDomainException('Ресторан не найден на выбранном сервере.', 422);
        }

        $scope = [];
        $foundCategories = [];

        foreach ($catalog->categories as $category) {
            if (! isset($wanted[$category->id])) {
                continue;
            }
            $foundCategories[$category->id] = true;
            foreach ($category->dishes as $dish) {
                $scope[] = [
                    'id' => $dish->id,
                    'name' => $dish->name,
                    'category_id' => $category->id,
                    'category_name' => $category->name,
                ];
            }
        }

        foreach (array_keys($wanted) as $categoryId) {
            if (! isset($foundCategories[$categoryId])) {
                throw new FoodDomainException(
                    'Категория меню не найдена для выбранного ресторана.',
                    422,
                );
            }
        }

        if ($scope === []) {
            throw new FoodDomainException(
                'В выбранных категориях нет блюд для OCR графика.',
                422,
            );
        }

        return $scope;
    }
}
