<?php

declare(strict_types=1);

namespace App\Contracts\Food;

use App\DTO\Food\AdminDishDto;
use App\DTO\Food\CreateDishDto;
use App\DTO\Food\ImportDishRowDto;
use App\DTO\Food\UpdateDishDto;
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
    public function list(?int $restaurantId = null, ?int $categoryId = null, ?string $nameSearch = null): array;

    /**
     * @throws FoodDomainException
     */
    public function show(int $dishId): AdminDishDto;

    /**
     * @throws FoodDomainException
     */
    public function create(CreateDishDto $dto, UploadedFile $photo): AdminDishDto;

    /**
     * Импорт строки из таблицы: при точном совпадении названия обновляет только цену.
     *
     * @throws FoodDomainException
     */
    public function importSpreadsheetRow(ImportDishRowDto $row, int $menuCategoryId): void;

    /**
     * Создание блюда с placeholder-изображением (импорт из таблицы).
     *
     * @throws FoodDomainException
     */
    public function createWithDefaultImage(CreateDishDto $dto): AdminDishDto;

    /**
     * @throws FoodDomainException
     */
    public function update(int $dishId, UpdateDishDto $dto, ?UploadedFile $photo = null): AdminDishDto;

    /**
     * @throws FoodDomainException
     */
    public function delete(int $dishId): void;
}
