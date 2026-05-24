<?php

namespace App\Services\SalesOutlets;

use App\DTO\SalesOutlets\SalesOutletIndexQueryDto;
use App\DTO\SalesOutlets\SalesOutletRowDto;
use App\DTO\SalesOutlets\UpdateHeadOrganizationDto;
use App\DTO\SalesOutlets\UpdateSalesOutletDto;
use App\Models\SalesOutlet;

interface SalesOutletServiceInterface
{
    /**
     * @return array<int, array<string, bool|int|string>>
     */
    public function columns(): array;

    /**
     * @return array<int, string>
     */
    public function allowedColumnKeys(): array;

    /**
     * @return array<string, mixed>
     */
    public function index(SalesOutletIndexQueryDto $queryDto): array;

    public function updateHeadOrganization(SalesOutlet $salesOutlet, UpdateHeadOrganizationDto $dto): SalesOutletRowDto;

    public function update(SalesOutlet $salesOutlet, UpdateSalesOutletDto $dto): SalesOutletRowDto;

    public function delete(SalesOutlet $salesOutlet): void;
}
