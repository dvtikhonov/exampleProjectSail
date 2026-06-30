<?php

declare(strict_types=1);

namespace App\Contract\SalesOutlets;

use App\Domain\SalesOutlet;
use App\DTO\SalesOutlets\SalesOutletIndexQueryDto;
use App\DTO\SalesOutlets\SalesOutletPaginatedResultDto;
use App\DTO\SalesOutlets\UpdateHeadOrganizationDto;
use App\DTO\SalesOutlets\UpdateSalesOutletDto;

/** Контракт репозитория торговых точек (доменная модель). */
interface SalesOutletRepositoryInterface
{
    /** Находит активную (не удалённую) торговую точку по id. */
    public function findById(int $id): ?SalesOutlet;

    /** Возвращает страницу торговых точек с учётом фильтров и сортировки. */
    public function paginate(SalesOutletIndexQueryDto $queryDto): SalesOutletPaginatedResultDto;

    public function updateHeadOrganization(SalesOutlet $salesOutlet, UpdateHeadOrganizationDto $dto): SalesOutlet;

    public function update(SalesOutlet $salesOutlet, UpdateSalesOutletDto $dto): SalesOutlet;

    public function delete(SalesOutlet $salesOutlet): void;
}
