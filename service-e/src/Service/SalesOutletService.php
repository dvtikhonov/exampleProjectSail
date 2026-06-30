<?php

declare(strict_types=1);

namespace App\Service;

use App\Contract\SalesOutlets\SalesOutletRepositoryInterface;
use App\Contract\SalesOutlets\SalesOutletServiceInterface;
use App\Domain\SalesOutlet;
use App\DTO\SalesOutlets\SalesOutletIndexQueryDto;
use App\DTO\SalesOutlets\SalesOutletIndexResultDto;
use App\DTO\SalesOutlets\UpdateHeadOrganizationDto;
use App\DTO\SalesOutlets\UpdateSalesOutletDto;

/**
 * Сервисный слой торговых точек: делегирует операции репозиторию.
 */
class SalesOutletService implements SalesOutletServiceInterface
{
    public function __construct(
        private readonly SalesOutletRepositoryInterface $salesOutletRepository,
    ) {
    }

    public function index(SalesOutletIndexQueryDto $queryDto): SalesOutletIndexResultDto
    {
        $paginatedResult = $this->salesOutletRepository->paginate($queryDto);

        return SalesOutletIndexResultDto::fromPaginatedResult($paginatedResult, $queryDto);
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
