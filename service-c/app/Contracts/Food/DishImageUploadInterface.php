<?php

declare(strict_types=1);

namespace App\Contracts\Food;

use App\Exceptions\Food\FoodDomainException;
use Illuminate\Http\UploadedFile;

/**
 * Загрузка и удаление локальных фото блюд на public disk.
 */
interface DishImageUploadInterface
{
    /**
     * Сохраняет фото блюда и возвращает относительный путь внутри public disk.
     *
     * @throws FoodDomainException
     */
    public function upload(int $dishId, UploadedFile $file): string;

    /**
     * Удаляет файл с public disk, если путь задан и файл существует.
     */
    public function deleteIfExists(?string $relativePath): void;
}
