<?php

declare(strict_types=1);

namespace App\Contracts\Food\Menu;

/**
 * Полный репозиторий блюд для административного CRUD.
 *
 * Composition ISP: объединяет read / write / bulk порты.
 */
interface DishAdminRepositoryInterface extends DishAdminBulkRepositoryInterface, DishAdminReadRepositoryInterface, DishAdminWriteRepositoryInterface {}
