<?php

namespace Shared\SalesOutletsDomain\Query\SalesOutletFilterQuery;

use Shared\SalesOutletsDomain\AbstractFilter\QueryFilters\Composite\CompositeFilter;
use Shared\SalesOutletsDomain\AbstractFilter\QueryFilters\Contracts\Filter;
use Shared\SalesOutletsDomain\AbstractFilter\QueryFilters\Filters\WhereInFilter;
use Shared\SalesOutletsDomain\AbstractFilter\QueryFilters\Filters\WhereLikePrefixFilter;
use Shared\SalesOutletsDomain\Enums\SalesOutletStatus;
use Shared\SalesOutletsDomain\Metadata\SalesOutletColumns;

final class SalesOutletFilterFactory
{
    /**
     * @param  array<string, string>  $filterData
     */
    public function fromArrayData(array $filterData): Filter
    {
        $filters = [];

        foreach (SalesOutletColumns::likePrefixFilterColumnMap() as $columnKey => $dbColumn) {
            $filters[] = new WhereLikePrefixFilter(
                column: $dbColumn,
                prefix: $filterData[$columnKey] ?? '',
            );
        }

        $filterTypes = SalesOutletColumns::columnFilterTypeMap();

        if (($filterTypes['status_label'] ?? null) === SalesOutletColumns::FILTER_STATUS_LABEL) {
            $filters[] = new WhereInFilter(
                column: 'status',
                values: $this->statusesByLabel($filterData['status_label'] ?? ''),
            );
        }

        return new CompositeFilter($filters);
    }

    /**
     * @return array<int, string>
     */
    private function statusesByLabel(string $value): array
    {
        if ($value === '') {
            return [];
        }

        $needle = mb_strtolower($value);

        return array_values(array_map(
            fn (SalesOutletStatus $status): string => $status->value,
            array_filter(
                SalesOutletStatus::cases(),
                fn (SalesOutletStatus $status): bool => str_contains(
                    mb_strtolower($status->label()),
                    $needle,
                ),
            ),
        ));
    }
}
