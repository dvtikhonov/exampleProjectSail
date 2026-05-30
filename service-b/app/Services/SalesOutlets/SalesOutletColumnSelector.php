<?php

namespace App\Services\SalesOutlets;

use App\Contracts\Repositories\SalesOutlets\SalesOutletsMetadataRepositoryInterface;
use App\DTO\SalesOutlets\SalesOutletReportFilterDto;

class SalesOutletColumnSelector
{
    public function __construct(
        private readonly SalesOutletsMetadataRepositoryInterface $metadataRepository,
    ) {}

    /**
     * @return array<int, array<string, bool|int|string>>
     */
    public function select(SalesOutletReportFilterDto $filters): array
    {
        return array_values(array_filter(
            $this->metadataRepository->columns(),
            fn (array $column): bool => in_array($column['key'], $filters->columns, true),
        ));
    }
}
