<?php

declare(strict_types=1);

namespace App\Contracts\Food\Menu;

use App\DTO\Food\Menu\AdminDishDto;
use App\DTO\Food\Menu\CreateDishDto;
use App\DTO\Food\Menu\ImportDishRowDto;
use App\DTO\Food\Menu\UpdateDishDto;
use App\Enums\Food\Menu\AdminDishAvailabilityFilter;
use App\Exceptions\Food\FoodDomainException;
use Illuminate\Http\UploadedFile;

/**
 * Административный CRUD блюд меню.
 */
interface DishAdminServiceInterface
{
    /**
     * @return list<AdminDishDto>
     */
    public function list(
        ?int $restaurantId = null,
        ?int $categoryId = null,
        ?string $nameSearch = null,
        AdminDishAvailabilityFilter $availability = AdminDishAvailabilityFilter::All,
    ): array;

    /**
     * @throws FoodDomainException
     */
    public function show(int $dishId): AdminDishDto;

    /**
     * @throws FoodDomainException
     */
    public function create(CreateDishDto $dto, UploadedFile $photo): AdminDishDto;

    /**
     * Пакетный импорт строк из таблицы: при точном совпадении названия обновляет только цену.
     *
     * @param  list<ImportDishRowDto>  $rows
     *
     * @throws FoodDomainException
     */
    public function importSpreadsheetRows(array $rows, int $menuCategoryId): int;

    /**
     * @throws FoodDomainException
     */
    public function update(int $dishId, UpdateDishDto $dto, ?UploadedFile $photo = null): AdminDishDto;

    /**
     * @throws FoodDomainException
     */
    public function delete(int $dishId): void;
}
