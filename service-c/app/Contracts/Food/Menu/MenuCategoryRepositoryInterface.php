<?php

declare(strict_types=1);

namespace App\Contracts\Food\Menu;

/**
 * Полный репозиторий категорий меню для административного API.
 *
 * Composition ISP: объединяет read / write порты.
 */
interface MenuCategoryRepositoryInterface extends
    MenuCategoryReadRepositoryInterface,
    MenuCategoryWriteRepositoryInterface
{
}
