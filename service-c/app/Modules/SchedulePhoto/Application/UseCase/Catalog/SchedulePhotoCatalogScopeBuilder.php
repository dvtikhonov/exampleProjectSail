<?php

declare(strict_types=1);

namespace App\Modules\SchedulePhoto\Application\UseCase\Catalog;

use App\Modules\SchedulePhoto\Application\Contract\SchedulePhotoCatalogScopeBuilderInterface;
use App\Modules\SchedulePhoto\Application\Port\SchedulePhotoPhotoTextClientInterface;
use App\Modules\SchedulePhoto\Application\Dto\SchedulePhotoCatalogScopeDishDto;
use App\Modules\SchedulePhoto\Domain\Exception\SchedulePhotoDomainException;

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
            throw new SchedulePhotoDomainException('Нужна хотя бы одна категория для OCR графика.', 422);
        }

        $wanted = [];
        foreach ($categoryIds as $id) {
            $wanted[(int) $id] = true;
        }

        $catalog = $this->photoText->getCatalog($baseUrl, $restaurantId);
        if ($catalog->restaurantId !== $restaurantId) {
            throw new SchedulePhotoDomainException('Ресторан не найден на выбранном сервере.', 422);
        }

        $scope = [];
        $foundCategories = [];

        foreach ($catalog->categories as $category) {
            if (! isset($wanted[$category->id])) {
                continue;
            }
            $foundCategories[$category->id] = true;
            foreach ($category->dishes as $dish) {
                $scope[] = new SchedulePhotoCatalogScopeDishDto(
                    id: $dish->id,
                    name: $dish->name,
                    categoryId: $category->id,
                    categoryName: $category->name,
                );
            }
        }

        foreach (array_keys($wanted) as $categoryId) {
            if (! isset($foundCategories[$categoryId])) {
                throw new SchedulePhotoDomainException(
                    'Категория меню не найдена для выбранного ресторана.',
                    422,
                );
            }
        }

        if ($scope === []) {
            throw new SchedulePhotoDomainException(
                'В выбранных категориях нет блюд для OCR графика.',
                422,
            );
        }

        return $scope;
    }
}
