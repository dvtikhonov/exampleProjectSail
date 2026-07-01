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

    /** Обновляет головную организацию и возвращает доменную модель. */
    public function updateHeadOrganization(SalesOutlet $salesOutlet, UpdateHeadOrganizationDto $dto): SalesOutlet;

    /** Обновляет поля торговой точки и возвращает доменную модель. */
    public function update(SalesOutlet $salesOutlet, UpdateSalesOutletDto $dto): SalesOutlet;

    /** Помечает торговую точку удалённой (soft delete). */
    public function delete(SalesOutlet $salesOutlet): void;
}
