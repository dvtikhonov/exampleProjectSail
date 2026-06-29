<?php

declare(strict_types=1);

namespace App\Contracts\Food;

use App\Models\Dish;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Репозиторий блюд меню для административного CRUD.
 */
interface DishRepositoryInterface
{
    public function findById(int $id): ?Dish;

    /**
     * Список блюд для админки с опциональными фильтрами.
     *
     * @return LengthAwarePaginator<int, Dish>
     */
    public function paginateForAdmin(
        ?int $restaurantId,
        ?int $categoryId,
        ?string $nameSearch = null,
        int $perPage = 50,
    ): LengthAwarePaginator;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): Dish;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(Dish $dish, array $attributes): Dish;

    public function delete(Dish $dish): void;

    /**
     * Проверяет, есть ли блюдо в черновых корзинах пользователей.
     */
    public function existsInDraftCarts(int $dishId): bool;
}
