<?php

declare(strict_types=1);

namespace App\Contracts\Food\Menu;

use App\DTO\Food\Menu\DishRecord;

/**
 * Чтение блюд для административных сценариев.
 */
interface DishAdminReadRepositoryInterface
{
    /**
     * Находит блюдо по идентификатору.
     */
    public function findById(int $id): ?DishRecord;

    /**
     * Ищет блюдо по точному совпадению названия в категории меню.
     */
    public function findByNameAndMenuCategoryId(string $name, int $menuCategoryId): ?DishRecord;

    /**
     * Ищет блюда по списку названий в категории (первое совпадение на имя, без soft-deleted).
     *
     * @param  list<string>  $names
     * @return array<string, DishRecord> keyed by name
     */
    public function findByNamesAndMenuCategoryId(array $names, int $menuCategoryId): array;

    /**
     * Список блюд для админки с опциональными фильтрами.
     * Без ресторана и категории — не более 10 записей; при выбранном ресторане — без лимита.
     *
     * @return list<DishRecord>
     */
    public function listForAdmin(
        ?int $restaurantId,
        ?int $categoryId,
        ?string $nameSearch = null,
        ?bool $isAvailable = null,
    ): array;

    /**
     * Проверяет, есть ли блюдо в черновых корзинах пользователей.
     */
    public function existsInDraftCarts(int $dishId): bool;
}
