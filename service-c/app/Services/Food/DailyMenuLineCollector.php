<?php

declare(strict_types=1);

namespace App\Services\Food;

use App\Contracts\Food\DailyMenuCatalogRepositoryInterface;
use App\Contracts\Food\DailyMenuLineCollectorInterface;
use App\DTO\Food\DailyMenuDishPartDto;
use App\DTO\Food\DailyMenuLineDto;
use App\Enums\Food\DailyMenuLineType;
use App\Enums\Food\DishWeightUnit;
use App\Models\Dish;
use App\Models\MenuCategory;

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
     * @param  list<Dish>  $dishes
     * @return array<int, list<Dish>>
     */
    private function groupByRestaurant(array $dishes): array
    {
        $grouped = [];

        foreach ($dishes as $dish) {
            $restaurantId = (int) ($dish->menuCategory?->restaurant_id ?? 0);

            if ($restaurantId === 0) {
                continue;
            }

            $grouped[$restaurantId][] = $dish;
        }

        ksort($grouped);

        return $grouped;
    }

    /**
     * @param  list<Dish>  $dishes
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

            $categoryId = (int) $category->id;

            if ((bool) $category->is_combo_available) {
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
     * @param  list<array{category: MenuCategory, dishes: list<Dish>}>  $groups
     * @return list<array{category: MenuCategory, dishes: list<Dish>}>
     */
    private function sortCategoryGroups(array $groups): array
    {
        usort(
            $groups,
            static function (array $left, array $right): int {
                $sortCmp = ((int) $left['category']->sort_order) <=> ((int) $right['category']->sort_order);

                if ($sortCmp !== 0) {
                    return $sortCmp;
                }

                return ((int) $left['category']->id) <=> ((int) $right['category']->id);
            },
        );

        foreach ($groups as &$group) {
            usort(
                $group['dishes'],
                static fn (Dish $left, Dish $right): int => $left->id <=> $right->id,
            );
        }
        unset($group);

        return $groups;
    }

    private function singleLine(Dish $dish): DailyMenuLineDto
    {
        return new DailyMenuLineDto(
            type: DailyMenuLineType::Single,
            parts: [$this->toPart($dish)],
            quantity: 1,
        );
    }

    private function toPart(Dish $dish): DailyMenuDishPartDto
    {
        $description = trim((string) ($dish->description ?? ''));

        return new DailyMenuDishPartDto(
            name: trim((string) $dish->name),
            description: $description !== '' ? $description : null,
            weightLabel: $this->formatWeightLabel($dish),
            price: (float) $dish->price,
        );
    }

    private function formatWeightLabel(Dish $dish): ?string
    {
        if ($dish->weight === null || $dish->weight === '') {
            return null;
        }

        $unit = $dish->weight_unit instanceof DishWeightUnit
            ? $dish->weight_unit
            : DishWeightUnit::Gram;

        return sprintf('%s%s', (string) (int) round((float) $dish->weight), $unit->label());
    }
}
