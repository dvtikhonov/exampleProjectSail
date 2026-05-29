<?php

namespace App\Services\SalesOutlets;

use App\DTO\SalesOutlets\SalesOutletExportFilterDto;
use App\Repositories\SalesOutlets\SalesOutletsExportMetadataRepositoryInterface;

class SalesOutletColumnSelector
{
    public function __construct(
        private readonly SalesOutletsExportMetadataRepositoryInterface $metadataRepository,
    ) {}

    /**
     * @return array<int, array<string, bool|int|string>>
     */
    public function select(SalesOutletExportFilterDto $filters): array
    {
        return array_values(array_filter(
            $this->metadataRepository->columns(),
            fn (array $column): bool => in_array($column['key'], $filters->columns, true),
        ));
    }
}
