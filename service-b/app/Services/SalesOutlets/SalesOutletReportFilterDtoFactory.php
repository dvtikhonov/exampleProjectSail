<?php

namespace App\Services\SalesOutlets;

use App\Contracts\Repositories\SalesOutlets\SalesOutletsMetadataRepositoryInterface;
use App\Contracts\SalesOutlets\SalesOutletReportFilterDtoFactoryInterface;
use App\DTO\SalesOutlets\SalesOutletReportFilterDto;

class SalesOutletReportFilterDtoFactory implements SalesOutletReportFilterDtoFactoryInterface
{
    public function __construct(
        private readonly SalesOutletsMetadataRepositoryInterface $metadataRepository,
    ) {}

    /**
     * @param  array<string, mixed>  $validated
     */
    public function fromValidated(array $validated): SalesOutletReportFilterDto
    {
        return SalesOutletReportFilterDto::fromValidated(
            validated: $validated,
            allowedColumns: $this->metadataRepository->allowedColumnKeys(),
        );
    }
}
