<?php

declare(strict_types=1);

namespace App\Contracts\Food\Menu;

use App\DTO\Food\Menu\DishRecord;

/**
 * Репозиторий блюд для административного CRUD.
 */
interface DishAdminRepositoryInterface
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
     * Пакетно обновляет цены блюд по id.
     *
     * @param  array<int, string>  $pricesById  dishId => price
     */
    public function updatePricesByIds(array $pricesById): void;

    /**
     * Пакетно создаёт блюда и возвращает созданные проекции с id.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return list<DishRecord>
     */
    public function createMany(array $rows): array;

    /**
     * Пакетно обновляет image_url блюд по id.
     *
     * @param  array<int, string>  $imageUrlsById  dishId => image_url
     */
    public function updateImageUrlsByIds(array $imageUrlsById): void;

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
     * Создаёт блюдо.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): DishRecord;

    /**
     * Обновляет блюдо по идентификатору.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function update(int $dishId, array $attributes): DishRecord;

    /**
     * Удаляет блюдо по идентификатору.
     */
    public function delete(int $dishId): void;

    /**
     * Проверяет, есть ли блюдо в черновых корзинах пользователей.
     */
    public function existsInDraftCarts(int $dishId): bool;
}
