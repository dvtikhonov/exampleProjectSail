<?php

namespace App\Services\SalesOutlets;

use App\Contracts\Repositories\SalesOutlets\SalesOutletsMetadataRepositoryInterface;
use App\Contracts\SalesOutlets\SalesOutletTableMetaProviderInterface;
use Shared\SalesOutletsDomain\Enums\SalesOutletStatus;

class ConfigSalesOutletTableMetaProvider implements SalesOutletTableMetaProviderInterface
{
    public function __construct(
        private readonly SalesOutletsMetadataRepositoryInterface $metadataRepository,
    ) {}

    /**
     * @return array<int, array<string, bool|int|string>>
     */
    public function columns(): array
    {
        /** @var array<string, array<string, bool|int|string>> $columnsUi */
        $columnsUi = config('sales_outlets.columns_ui', []);

        return array_map(
            fn (array $column): array => array_merge($column, $columnsUi[$column['key']] ?? []),
            $this->metadataRepository->columns(),
        );
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public function statusOptions(): array
    {
        return array_merge(
            [['value' => '', 'label' => (string) config('sales_outlets.status_options_all_label', 'Все статусы')]],
            SalesOutletStatus::options(),
        );
    }
}
