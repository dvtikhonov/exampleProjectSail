<?php

declare(strict_types=1);

namespace App\Repositories\Food\Menu;

use App\DTO\Food\Menu\CreateDishDto;
use App\DTO\Food\Menu\CreateMenuCategoryDto;
use App\DTO\Food\Menu\DishRecord;
use App\DTO\Food\Menu\MenuCategoryAvailabilityOffsetDto;
use App\DTO\Food\Menu\MenuCategoryRecord;
use App\DTO\Food\Menu\RestaurantSummaryRecord;
use App\DTO\Food\Menu\RestaurantWithMenuRecord;
use App\DTO\Food\Menu\UpdateDishDto;
use App\DTO\Food\Menu\UpdateMenuCategoryDto;
use App\Enums\Food\Menu\DishWeightUnit;
use App\Enums\Food\Menu\Weekday;
use App\Models\Food\Dish;
use App\Models\Food\MenuCategory;
use App\Models\Food\MenuCategoryAvailabilityOffset;
use App\Models\Food\Restaurant;
use Illuminate\Support\Collection;

/**
 * Преобразование между Eloquent-моделями меню и доменными Record/DTO.
 */
class DishMapper
{
    /**
     * Атрибуты Eloquent для создания блюда (без фото).
     *
     * @return array<string, mixed>
     */
    public function toCreateAttributes(CreateDishDto $dto): array
    {
        return [
            ...$this->toBaseDishAttributes($dto),
            'image_url' => null,
        ];
    }

    /**
     * Атрибуты Eloquent для обновления полей блюда (без image_url).
     *
     * @return array<string, mixed>
     */
    public function toUpdateAttributes(UpdateDishDto $dto): array
    {
        return $this->toBaseDishAttributes($dto);
    }

    /**
     * Атрибуты Eloquent для создания категории меню.
     *
     * @return array<string, mixed>
     */
    public function toCreateCategoryAttributes(CreateMenuCategoryDto $dto, int $sortOrder): array
    {
        return [
            'restaurant_id' => $dto->restaurantId,
            'name' => $dto->name,
            'sort_order' => $sortOrder,
            'is_combo_available' => $dto->isComboAvailable,
        ];
    }

    /**
     * Атрибуты Eloquent для обновления категории меню.
     *
     * @return array<string, mixed>
     */
    public function toUpdateCategoryAttributes(UpdateMenuCategoryDto $dto): array
    {
        return [
            'restaurant_id' => $dto->restaurantId,
            'name' => $dto->name,
            'sort_order' => $dto->sortOrder,
            'is_combo_available' => $dto->isComboAvailable,
        ];
    }

    /**
     * Преобразует модель блюда в доменную проекцию.
     */
    public function toRecord(Dish $model): DishRecord
    {
        return new DishRecord(
            id: (int) $model->id,
            menuCategoryId: (int) $model->menu_category_id,
            name: (string) $model->name,
            description: $model->description !== null ? (string) $model->description : null,
            weight: (string) $model->weight,
            weightUnit: $model->weight_unit ?? DishWeightUnit::Gram,
            imageUrl: $model->image_url !== null ? (string) $model->image_url : null,
            price: (string) $model->price,
            vatRate: $model->vat_rate !== null ? (int) $model->vat_rate : null,
            isAvailable: (bool) $model->is_available,
            menuCategory: $model->relationLoaded('menuCategory') && $model->menuCategory !== null
                ? $this->toCategoryRecord($model->menuCategory)
                : null,
        );
    }

    /**
     * Преобразует модель категории меню в доменную проекцию.
     *
     * @param  bool  $withDishes  Включать блюда, если отношение dishes загружено
     */
    public function toCategoryRecord(MenuCategory $model, bool $withDishes = false): MenuCategoryRecord
    {
        $dishes = [];

        if ($withDishes && $model->relationLoaded('dishes')) {
            $dishes = $model->dishes
                ->map(fn (Dish $dish): DishRecord => $this->toRecord($dish))
                ->values()
                ->all();
        }

        return new MenuCategoryRecord(
            id: (int) $model->id,
            restaurantId: (int) $model->restaurant_id,
            name: (string) $model->name,
            sortOrder: (int) $model->sort_order,
            isComboAvailable: (bool) $model->is_combo_available,
            restaurant: $model->relationLoaded('restaurant') && $model->restaurant !== null
                ? $this->toRestaurantSummary($model->restaurant)
                : null,
            availabilityOffsets: $model->relationLoaded('availabilityOffsets')
                ? $this->mapAvailabilityOffsets($model)
                : [],
            dishes: $dishes,
        );
    }

    /**
     * Краткая проекция ресторана.
     */
    public function toRestaurantSummary(Restaurant $model): RestaurantSummaryRecord
    {
        return new RestaurantSummaryRecord(
            id: (int) $model->id,
            name: (string) $model->name,
            isActive: (bool) $model->is_active,
            address: (string) ($model->address ?? ''),
        );
    }

    /**
     * Ресторан с вложенными категориями и блюдами для клиентского меню.
     */
    public function toRestaurantWithMenu(Restaurant $model): RestaurantWithMenuRecord
    {
        $categories = [];

        if ($model->relationLoaded('menuCategories')) {
            $categories = $model->menuCategories
                ->map(fn (MenuCategory $category): MenuCategoryRecord => $this->toCategoryRecord($category, true))
                ->values()
                ->all();
        }

        return new RestaurantWithMenuRecord(
            id: (int) $model->id,
            name: (string) $model->name,
            menuCategories: $categories,
        );
    }

    /**
     * Базовые атрибуты блюда из Create/Update DTO.
     *
     * @return array<string, mixed>
     */
    private function toBaseDishAttributes(CreateDishDto|UpdateDishDto $dto): array
    {
        return [
            'menu_category_id' => $dto->menuCategoryId,
            'name' => $dto->name,
            'description' => $dto->description,
            'weight' => $dto->weight,
            'weight_unit' => $dto->weightUnit->value,
            'price' => $dto->price,
            'vat_rate' => $dto->vatRate->value(),
            'is_available' => $dto->isAvailable,
        ];
    }

    /**
     * Группирует строки смещений по group_key в список правил.
     *
     * @return list<MenuCategoryAvailabilityOffsetDto>
     */
    private function mapAvailabilityOffsets(MenuCategory $category): array
    {
        /** @var Collection<string, Collection<int, MenuCategoryAvailabilityOffset>> $grouped */
        $grouped = $category->availabilityOffsets
            ->groupBy(static fn (MenuCategoryAvailabilityOffset $row): string => (string) $row->group_key);

        $result = [];

        foreach ($grouped as $rows) {
            /** @var list<int> $weekdays */
            $weekdays = $rows
                ->map(static function (MenuCategoryAvailabilityOffset $row): int {
                    $weekday = $row->weekday;

                    return $weekday instanceof Weekday ? $weekday->value : (int) $weekday;
                })
                ->unique()
                ->sort()
                ->values()
                ->all();

            /** @var MenuCategoryAvailabilityOffset $first */
            $first = $rows->first();

            $result[] = new MenuCategoryAvailabilityOffsetDto(
                weekdays: $weekdays,
                offsetDays: (int) $first->offset_days,
            );
        }

        return $result;
    }
}
