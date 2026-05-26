<?php

namespace Shared\SalesOutletsDomain\Query\SalesOutletFilterQuery;

use Shared\SalesOutletsDomain\AbstractFilter\QueryFilters\Composite\CompositeFilter;
use Shared\SalesOutletsDomain\AbstractFilter\QueryFilters\Contracts\Filter;
use Shared\SalesOutletsDomain\AbstractFilter\QueryFilters\Filters\WhereInFilter;
use Shared\SalesOutletsDomain\AbstractFilter\QueryFilters\Filters\WhereLikePrefixFilter;
use Shared\SalesOutletsDomain\Enums\SalesOutletStatus;

final class SalesOutletFilterFactory
{
    /**
     * @var array<int, string>
     */
    private const LIKE_PREFIX_COLUMNS = [
        'id',
        'shop',
        'manager',
        'curator',
        'name',
        'inn',
        'head_organization',
        'head_organization_type',
        'organization_name',
        'approved',
//        'user_id',
    ];

    /**
     * @param array<string, string> $filterData
     */
    public function fromArrayData(array $filterData): Filter
    {
        $filters = [];

        foreach (self::LIKE_PREFIX_COLUMNS as $column) {
            $filters[] = new WhereLikePrefixFilter(
                column: $column,
                prefix: $filterData[$column] ?? '',
            );
        }

        $filters[] = new WhereInFilter(
            column: 'status',
            values: $this->statusesByLabel($filterData['status_label'] ?? ''),
        );

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

