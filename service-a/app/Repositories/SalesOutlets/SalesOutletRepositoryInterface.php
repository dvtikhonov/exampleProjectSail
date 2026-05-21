<?php

namespace App\Repositories\SalesOutlets;

use App\DTO\SalesOutlets\SalesOutletIndexQueryDto;
use App\DTO\SalesOutlets\UpdateHeadOrganizationDto;
use App\Models\SalesOutlet;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface SalesOutletRepositoryInterface
{
    /**
     * @param  array<int, string>  $allowedColumnKeys
     */
    public function paginate(SalesOutletIndexQueryDto $queryDto, array $allowedColumnKeys): LengthAwarePaginator;

    public function updateHeadOrganization(SalesOutlet $salesOutlet, UpdateHeadOrganizationDto $dto): SalesOutlet;

    public function delete(SalesOutlet $salesOutlet): void;
}
