<?php

declare(strict_types=1);

namespace App\Services\Food\Menu;

use App\Contracts\Food\Menu\DailyMenuCatalogRepositoryInterface;
use App\Contracts\Food\Menu\DailyMenuLineCollectorInterface;
use App\DTO\Food\Menu\DailyMenuDishPartDto;
use App\DTO\Food\Menu\DailyMenuLineDto;
use App\DTO\Food\Menu\DishRecord;
use App\DTO\Food\Menu\MenuCategoryRecord;
use App\Enums\Food\Menu\DailyMenuLineType;
use App\Enums\Food\Menu\DishWeightUnit;

/**
 * Собирает позиции ежедневного меню: одиночные блюда и комбо-пары.
 *
 * Комбо — декартово произведение доступных блюд из разных категорий
 * с is_combo_available=true одного ресторана (без дублей A/B и B/A).
 * Категории без комбо и «осиротевшие» комбо-категории без пары — одиночные позиции.
 */
class DailyMenuLineCollector implements DailyMenuLineCollectorInterface
{
    public function __construct(
        private readonly DailyMenuCatalogRepositoryInterface $catalogRepository,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function collect(): array
    {
        $dishes = $this->catalogRepository->listAvailableWithCategories();
        $byRestaurant = $this->groupByRestaurant($dishes);
        $lines = [];

        foreach ($byRestaurant as $restaurantDishes) {
            $lines = array_merge($lines, $this->collectForRestaurant($restaurantDishes));
        }

        return $lines;
    }

    /**
     * @param  list<DishRecord>  $dishes
     * @return array<int, list<DishRecord>>
     */
    private function groupByRestaurant(array $dishes): array
    {
        $grouped = [];

        foreach ($dishes as $dish) {
            $restaurantId = $dish->menuCategory?->restaurantId ?? 0;

            if ($restaurantId === 0) {
                continue;
            }

            $grouped[$restaurantId][] = $dish;
        }

        ksort($grouped);

        return $grouped;
    }

    /**
     * @param  list<DishRecord>  $dishes
     * @return list<DailyMenuLineDto>
     */
    private function collectForRestaurant(array $dishes): array
    {
        $standaloneByCategory = [];
        $comboByCategory = [];

        foreach ($dishes as $dish) {
            $category = $dish->menuCategory;

            if ($category === null) {
                continue;
            }

            $categoryId = $category->id;

            if ($category->isComboAvailable) {
                $comboByCategory[$categoryId]['category'] = $category;
                $comboByCategory[$categoryId]['dishes'][] = $dish;
            } else {
                $standaloneByCategory[$categoryId]['category'] = $category;
                $standaloneByCategory[$categoryId]['dishes'][] = $dish;
            }
        }

        $comboGroups = $this->sortCategoryGroups(array_values($comboByCategory));
        $standaloneGroups = $this->sortCategoryGroups(array_values($standaloneByCategory));
        $lines = [];

        foreach ($standaloneGroups as $group) {
            foreach ($group['dishes'] as $dish) {
                $lines[] = $this->singleLine($dish);
            }
        }

        if (count($comboGroups) < 2) {
            foreach ($comboGroups as $group) {
                foreach ($group['dishes'] as $dish) {
                    $lines[] = $this->singleLine($dish);
                }
            }

            return $lines;
        }

        $groupCount = count($comboGroups);

        for ($i = 0; $i < $groupCount; $i++) {
            for ($j = $i + 1; $j < $groupCount; $j++) {
                foreach ($comboGroups[$i]['dishes'] as $firstDish) {
                    foreach ($comboGroups[$j]['dishes'] as $secondDish) {
                        $lines[] = new DailyMenuLineDto(
                            type: DailyMenuLineType::Combo,
                            parts: [
                                $this->toPart($firstDish),
                                $this->toPart($secondDish),
                            ],
                            quantity: 1,
                        );
                    }
                }
            }
        }

        return $lines;
    }

    /**
     * @param  list<array{category: MenuCategoryRecord, dishes: list<DishRecord>}>  $groups
     * @return list<array{category: MenuCategoryRecord, dishes: list<DishRecord>}>
     */
    private function sortCategoryGroups(array $groups): array
    {
        usort(
            $groups,
            static function (array $left, array $right): int {
                $sortCmp = $left['category']->sortOrder <=> $right['category']->sortOrder;

                if ($sortCmp !== 0) {
                    return $sortCmp;
                }

                return $left['category']->id <=> $right['category']->id;
            },
        );

        foreach ($groups as &$group) {
            usort(
                $group['dishes'],
                static fn (DishRecord $left, DishRecord $right): int => $left->id <=> $right->id,
            );
        }
        unset($group);

        return $groups;
    }

    private function singleLine(DishRecord $dish): DailyMenuLineDto
    {
        return new DailyMenuLineDto(
            type: DailyMenuLineType::Single,
            parts: [$this->toPart($dish)],
            quantity: 1,
        );
    }

    private function toPart(DishRecord $dish): DailyMenuDishPartDto
    {
        $description = trim((string) ($dish->description ?? ''));

        return new DailyMenuDishPartDto(
            name: trim($dish->name),
            description: $description !== '' ? $description : null,
            weightLabel: $this->formatWeightLabel($dish),
            price: (float) $dish->price,
        );
    }

    private function formatWeightLabel(DishRecord $dish): ?string
    {
        if ($dish->weight === '') {
            return null;
        }

        $unit = $dish->weightUnit instanceof DishWeightUnit
            ? $dish->weightUnit
            : DishWeightUnit::Gram;

        return sprintf('%s%s', (string) (int) round((float) $dish->weight), $unit->label());
    }
}
