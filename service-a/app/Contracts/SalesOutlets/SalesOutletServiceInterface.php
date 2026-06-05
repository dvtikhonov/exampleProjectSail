<?php

namespace App\Contracts\SalesOutlets;

use App\Domain\SalesOutlets\SalesOutlet;
use App\DTO\SalesOutlets\SalesOutletIndexQueryDto;
use App\DTO\SalesOutlets\SalesOutletIndexResultDto;
use App\DTO\SalesOutlets\UpdateHeadOrganizationDto;
use App\DTO\SalesOutlets\UpdateSalesOutletDto;

interface SalesOutletServiceInterface
{
    public function index(SalesOutletIndexQueryDto $queryDto): SalesOutletIndexResultDto;

    public function updateHeadOrganization(SalesOutlet $salesOutlet, UpdateHeadOrganizationDto $dto): SalesOutlet;

    public function update(SalesOutlet $salesOutlet, UpdateSalesOutletDto $dto): SalesOutlet;

    public function delete(SalesOutlet $salesOutlet): void;
}
