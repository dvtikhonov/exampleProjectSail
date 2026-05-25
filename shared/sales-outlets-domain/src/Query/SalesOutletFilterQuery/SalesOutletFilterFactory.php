<?php

namespace Shared\SalesOutletsDomain\Query\SalesOutletFilterQuery;

use App\AbstractFilter\QueryFilters\Filters\WhereHasFilter;
use App\AbstractFilter\QueryFilters\Filters\WhereInFilter;
use App\AbstractFilter\QueryFilters\Composite\CompositeFilter;
use App\AbstractFilter\QueryFilters\Contracts\Filter;

final class SalesOutletFilterFactory
{
    public function fromArrayData(array $filterData): Filter
    {
        $groupHead = $filterData['groupHead'] ?? [];
        $recruiter = $filterData['recruiter'] ?? [];
        $direction = $filterData['direction'] ?? [];

        return new CompositeFilter([
            new WhereHasFilter(
                relation: 'recruiter.operator',
                filter: new WhereInFilter(column: 'direction', values: $direction, enabled: true),
                enabled: count($direction) > 0, // true,
            ),
            new WhereHasFilter(
                relation: 'recruiter.operator',
                filter: new WhereInFilter(column: 'head', values: $groupHead, enabled: true),
                enabled: count($groupHead) > 0, // true,
            ),
            new WhereHasFilter(
                relation: 'recruiter',
                filter: new WhereInFilter(column: 'recruiter_id', values: $recruiter, enabled: true),
                enabled: count($recruiter) > 0, //true,
            ),
            new WhereInFilter(column: 'project_id', values: $filterData['project'] ?? [], enabled: true),
            new WhereInFilter(column: 'shop_id', values: $filterData['shops'] ?? [], enabled: true),
            new WhereInFilter(column: 'job_id', values: $filterData['jobs'] ?? [], enabled: true),
            new WhereInFilter(column: 'sales_point_id', values: $filterData['salesPoint'] ?? [], enabled: true),
        ]);
    }
}

