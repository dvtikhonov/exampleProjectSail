<?php

declare(strict_types=1);

namespace App\Contract\SalesOutlets;

use App\Domain\SalesOutlet;
use App\DTO\SalesOutlets\SalesOutletIndexQueryDto;
use App\DTO\SalesOutlets\SalesOutletIndexResultDto;
use App\DTO\SalesOutlets\UpdateHeadOrganizationDto;
use App\DTO\SalesOutlets\UpdateSalesOutletDto;

/** Контракт сервисного слоя торговых точек. */
interface SalesOutletServiceInterface
{
    /** Пагинированный список торговых точек по параметрам запроса. */
    public function index(SalesOutletIndexQueryDto $queryDto): SalesOutletIndexResultDto;

    /** Обновляет головную организацию торговой точки. */
    public function updateHeadOrganization(SalesOutlet $salesOutlet, UpdateHeadOrganizationDto $dto): SalesOutlet;

    /** Обновляет поля торговой точки. */
    public function update(SalesOutlet $salesOutlet, UpdateSalesOutletDto $dto): SalesOutlet;

    /** Помечает торговую точку удалённой (soft delete). */
    public function delete(SalesOutlet $salesOutlet): void;
}
