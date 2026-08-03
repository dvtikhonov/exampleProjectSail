<?php

declare(strict_types=1);

namespace App\Contracts\Food\Menu;

use App\Models\Food\Dish;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * Репозиторий блюд для административного CRUD.
 */
interface DishAdminRepositoryInterface
{
    /**
     * Находит блюдо по идентификатору.
     */
    public function findById(int $id): ?Dish;

    /**
     * Ищет блюдо по точному совпадению названия в категории меню.
     */
    public function findByNameAndMenuCategoryId(string $name, int $menuCategoryId): ?Dish;

    /**
     * Ищет блюда по списку названий в категории (первое совпадение на имя, без soft-deleted).
     *
     * @param  list<string>  $names
     * @return Collection<string, Dish> keyed by name
     */
    public function findByNamesAndMenuCategoryId(array $names, int $menuCategoryId): Collection;

    /**
     * Пакетно обновляет цены блюд по id.
     *
     * @param  array<int, string>  $pricesById  dishId => price
     */
    public function updatePricesByIds(array $pricesById): void;

    /**
     * Пакетно создаёт блюда и возвращает созданные модели с id.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return Collection<int, Dish>
     */
    public function createMany(array $rows): Collection;

    /**
     * Пакетно обновляет image_url блюд по id.
     *
     * @param  array<int, string>  $imageUrlsById  dishId => image_url
     */
    public function updateImageUrlsByIds(array $imageUrlsById): void;

    /**
     * Список блюд для админки с опциональными фильтрами.
     *
     * @return LengthAwarePaginator<int, Dish>
     */
    public function paginateForAdmin(
        ?int $restaurantId,
        ?int $categoryId,
        ?string $nameSearch = null,
        ?bool $isAvailable = null,
        int $perPage = 50,
    ): LengthAwarePaginator;

    /**
     * Создаёт блюдо.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): Dish;

    /**
     * Обновляет блюдо.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function update(Dish $dish, array $attributes): Dish;

    /**
     * Удаляет блюдо.
     */
    public function delete(Dish $dish): void;

    /**
     * Проверяет, есть ли блюдо в черновых корзинах пользователей.
     */
    public function existsInDraftCarts(int $dishId): bool;
}
