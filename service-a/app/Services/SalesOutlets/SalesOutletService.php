<?php

namespace App\Services\SalesOutlets;

use App\Contracts\Repositories\SalesOutlets\SalesOutletRepositoryInterface;
use App\Contracts\SalesOutlets\SalesOutletServiceInterface;
use App\Domain\SalesOutlets\SalesOutlet;
use App\DTO\SalesOutlets\SalesOutletIndexQueryDto;
use App\DTO\SalesOutlets\SalesOutletIndexResultDto;
use App\DTO\SalesOutlets\UpdateHeadOrganizationDto;
use App\DTO\SalesOutlets\UpdateSalesOutletDto;

class SalesOutletService implements SalesOutletServiceInterface
{
    public function __construct(
        private readonly SalesOutletRepositoryInterface $salesOutletRepository,
    ) {}

    public function index(SalesOutletIndexQueryDto $queryDto): SalesOutletIndexResultDto
    {
        $paginator = $this->salesOutletRepository->paginate($queryDto);

        return SalesOutletIndexResultDto::fromPaginator($paginator, $queryDto);
    }

    public function updateHeadOrganization(SalesOutlet $salesOutlet, UpdateHeadOrganizationDto $dto): SalesOutlet
    {
        return $this->salesOutletRepository->updateHeadOrganization($salesOutlet, $dto);
    }

    public function update(SalesOutlet $salesOutlet, UpdateSalesOutletDto $dto): SalesOutlet
    {
        return $this->salesOutletRepository->update($salesOutlet, $dto);
    }

    public function delete(SalesOutlet $salesOutlet): void
    {
        $this->salesOutletRepository->delete($salesOutlet);
    }
}
