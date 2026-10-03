<?php

declare(strict_types=1);

namespace App\Services\Food\BrisklySync;

use App\Contracts\Food\BrisklySync\BrisklySyncSourceCollectorInterface;
use App\Contracts\Food\Menu\DailyMenuCatalogRepositoryInterface;
use App\Contracts\Food\Menu\MenuCategoryReadRepositoryInterface;
use App\Contracts\Food\Shared\FoodMoneyFormatterInterface;
use App\Contracts\Food\Shared\RestaurantRepositoryInterface;
use App\DTO\Food\BrisklySync\SourceMenuLineDto;
use App\DTO\Food\Menu\DishRecord;
use App\DTO\Food\Menu\MenuCategoryRecord;
use App\Enums\Food\Menu\DailyMenuLineType;
use App\Exceptions\Food\FoodDomainException;

/**
 * Собирает source-линии VPS для Briskly sync (single + unordered combo).
 *
 * Логика комбо совпадает с DailyMenuLineCollector: декартово по combo-категориям
 * без дублей B/A; одна combo-категория → fallback в single.
 */
class BrisklySyncSourceCollector implements BrisklySyncSourceCollectorInterface
{
    public function __construct(
        private readonly DailyMenuCatalogRepositoryInterface $catalogRepository,
        private readonly RestaurantRepositoryInterface $restaurantRepository,
        private readonly MenuCategoryReadRepositoryInterface $menuCategoryRepository,
        private readonly FoodMoneyFormatterInterface $moneyFormatter,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function collectForRestaurant(
        int $restaurantId,
        ?int $vpsCategoryId = null,
        ?string $searchText = null,
    ): array {
        $this->assertActiveRestaurant($restaurantId);
        $this->assertCategoryBelongsToRestaurant($restaurantId, $vpsCategoryId);

        $dishes = $this->catalogRepository->listAvailableWithCategoriesForRestaurant($restaurantId);
        $lines = $this->buildLines($dishes);

        if ($vpsCategoryId !== null) {
            $lines = array_values(array_filter(
                $lines,
                static fn (array $item): bool => in_array($vpsCategoryId, $item['category_ids'], true),
            ));
        }

        $needle = $this->normalizeSearchText($searchText);

        if ($needle !== null) {
            $lines = array_values(array_filter(
                $lines,
                static function (array $item) use ($needle): bool {
                    return mb_stripos($item['line']->displayName, $needle, 0, 'UTF-8') !== false;
                },
            ));
        }

        return array_map(
            static fn (array $item): SourceMenuLineDto => $item['line'],
            $lines,
        );
    }

    /**
     * @throws FoodDomainException
     */
    private function assertActiveRestaurant(int $restaurantId): void
    {
        if ($this->restaurantRepository->findActiveById($restaurantId) === null) {
            throw new FoodDomainException('Ресторан не найден или неактивен.', 422);
        }
    }

    /**
     * @throws FoodDomainException
     */
    private function assertCategoryBelongsToRestaurant(int $restaurantId, ?int $vpsCategoryId): void
    {
        if ($vpsCategoryId === null) {
            return;
        }

        $category = $this->menuCategoryRepository->findById($vpsCategoryId);

        if ($category === null || $category->restaurantId !== $restaurantId) {
            throw new FoodDomainException('Категория меню не найдена для выбранного ресторана.', 422);
        }
    }

    private function normalizeSearchText(?string $searchText): ?string
    {
        if ($searchText === null) {
            return null;
        }

        $trimmed = trim($searchText);

        return $trimmed === '' ? null : $trimmed;
    }

    /**
     * @param  list<DishRecord>  $dishes
     * @return list<array{line: SourceMenuLineDto, category_ids: list<int>}>
     */
    private function buildLines(array $dishes): array
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
                $lines[] = $this->wrapSingle($dish);
            }
        }

        if (count($comboGroups) < 2) {
            foreach ($comboGroups as $group) {
                foreach ($group['dishes'] as $dish) {
                    $lines[] = $this->wrapSingle($dish);
                }
            }

            return $lines;
        }

        $groupCount = count($comboGroups);

        for ($i = 0; $i < $groupCount; $i++) {
            for ($j = $i + 1; $j < $groupCount; $j++) {
                foreach ($comboGroups[$i]['dishes'] as $firstDish) {
                    foreach ($comboGroups[$j]['dishes'] as $secondDish) {
                        $lines[] = $this->wrapCombo($firstDish, $secondDish);
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

    /**
     * @return array{line: SourceMenuLineDto, category_ids: list<int>}
     */
    private function wrapSingle(DishRecord $dish): array
    {
        $displayName = trim($dish->name);

        return [
            'line' => new SourceMenuLineDto(
                lineKey: $this->singleLineKey($dish->id),
                type: DailyMenuLineType::Single,
                displayName: $displayName,
                price: $this->moneyFormatter->format($dish->price),
                partDishIds: [$dish->id],
                brisklyCreateName: BrisklyCreateNameFormatter::forSingle(
                    $displayName,
                    $dish->weight,
                    $dish->weightUnit,
                ),
            ),
            'category_ids' => [$dish->menuCategoryId],
        ];
    }

    /**
     * @return array{line: SourceMenuLineDto, category_ids: list<int>}
     */
    private function wrapCombo(DishRecord $firstDish, DishRecord $secondDish): array
    {
        $firstName = trim($firstDish->name);
        $secondName = trim($secondDish->name);
        $priceSum = $this->moneyFormatter->formatCents(
            $this->moneyFormatter->toCents($firstDish->price)
            + $this->moneyFormatter->toCents($secondDish->price),
        );

        return [
            'line' => new SourceMenuLineDto(
                lineKey: $this->comboLineKey($firstDish->id, $secondDish->id),
                type: DailyMenuLineType::Combo,
                displayName: $firstName.' / '.$secondName,
                price: $priceSum,
                partDishIds: [$firstDish->id, $secondDish->id],
                brisklyCreateName: BrisklyCreateNameFormatter::forCombo(
                    $firstName,
                    $firstDish->weight,
                    $firstDish->weightUnit,
                    $secondName,
                    $secondDish->weight,
                    $secondDish->weightUnit,
                ),
            ),
            'category_ids' => [$firstDish->menuCategoryId, $secondDish->menuCategoryId],
        ];
    }

    private function singleLineKey(int $dishId): string
    {
        return 'single:'.$dishId;
    }

    private function comboLineKey(int $firstDishId, int $secondDishId): string
    {
        return 'combo:'.$firstDishId.':'.$secondDishId;
    }
}
