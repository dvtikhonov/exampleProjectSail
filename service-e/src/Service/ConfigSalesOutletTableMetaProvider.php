<?php

declare(strict_types=1);

namespace App\Service;

use App\Contract\SalesOutlets\SalesOutletsMetadataRepositoryInterface;
use App\Contract\SalesOutlets\SalesOutletTableMetaProviderInterface;
use Shared\SalesOutletsDomain\Enums\SalesOutletStatus;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Метаданные UI таблицы: колонки и опции статусов.
 * UI-настройки колонок берутся из config/packages/sales_outlets.yaml.
 */
class ConfigSalesOutletTableMetaProvider implements SalesOutletTableMetaProviderInterface
{
    /**
     * @param array<string, array<string, bool|int|string>> $columnsUi
     */
    public function __construct(
        private readonly SalesOutletsMetadataRepositoryInterface $metadataRepository,
        #[Autowire(param: 'sales_outlets.columns_ui')]
        private readonly array $columnsUi,
        #[Autowire(param: 'sales_outlets.status_options_all_label')]
        private readonly string $statusOptionsAllLabel,
    ) {
    }

    /**
     * {@inheritDoc}
     *
     * @return array<int, array<string, bool|int|string>>
     */
    public function columns(): array
    {
        return array_map(
            fn (array $column): array => array_merge($column, $this->columnsUi[$column['key']] ?? []),
            $this->metadataRepository->columns(),
        );
    }

    /**
     * {@inheritDoc}
     *
     * @return array<int, array{value: string, label: string}>
     */
    public function statusOptions(): array
    {
        return array_merge(
            [['value' => '', 'label' => $this->statusOptionsAllLabel]],
            SalesOutletStatus::options(),
        );
    }
}
