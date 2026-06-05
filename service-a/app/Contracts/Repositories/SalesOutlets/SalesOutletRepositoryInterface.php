<?php

namespace App\Contracts\Repositories\SalesOutlets;

use App\Domain\SalesOutlets\SalesOutlet;
use App\DTO\SalesOutlets\SalesOutletIndexQueryDto;
use App\DTO\SalesOutlets\UpdateHeadOrganizationDto;
use App\DTO\SalesOutlets\UpdateSalesOutletDto;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface SalesOutletRepositoryInterface
{
    public function findById(int $id): ?SalesOutlet;

    public function paginate(SalesOutletIndexQueryDto $queryDto): LengthAwarePaginator;

    public function updateHeadOrganization(SalesOutlet $salesOutlet, UpdateHeadOrganizationDto $dto): SalesOutlet;

    public function update(SalesOutlet $salesOutlet, UpdateSalesOutletDto $dto): SalesOutlet;

    public function delete(SalesOutlet $salesOutlet): void;
}
