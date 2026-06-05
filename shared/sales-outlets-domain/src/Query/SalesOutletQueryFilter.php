<?php

namespace Shared\SalesOutletsDomain\Query;

use Illuminate\Database\Eloquent\Builder;
use Shared\SalesOutletsDomain\DTO\SalesOutletFilterDto;
use Shared\SalesOutletsDomain\Enums\SalesOutletStatus;
use Shared\SalesOutletsDomain\Metadata\SalesOutletColumns;
use Shared\SalesOutletsDomain\Query\SalesOutletFilterQuery\FilterQuerySalesOutletComposite;

final class SalesOutletQueryFilter
{
    protected FilterQuerySalesOutletComposite $filterQuerySalesOutletComposite;

    public function __construct()
    {
        $this->filterQuerySalesOutletComposite = app(FilterQuerySalesOutletComposite::class, []);
    }

    /**
     * @param  array<int, string>  $allowedColumnKeys
     */
    public function apply(Builder $query, SalesOutletFilterDto $filters, array $allowedColumnKeys): void
    {
        $this->applyStatus($query, $filters->status);
        $this->applyColumnFilters($query, $filters->columnFilters, $allowedColumnKeys);
        $this->applySearch($query, $filters->search);
        $this->applySort($query, $filters->sort, $filters->direction);
    }

    private function applyStatus(Builder $query, string $status): void
    {
        if (SalesOutletStatus::tryFrom($status) === null) {
            return;
        }

        $query->where('status', $status);
    }

    /**
     * @param  array<string, string>  $columnFilters
     * @param  array<int, string>  $allowedColumnKeys
     */
    private function applyColumnFilters(Builder $query, array $columnFilters, array $allowedColumnKeys): void
    {
        $data = new \stdClass();
        $data->query = $query;
        $data->filterData = $columnFilters;

        $this->filterQuerySalesOutletComposite->run($data);
    }

    private function applySearch(Builder $query, string $search): void
    {
        if ($search === '') {
            return;
        }

        $searchColumns = SalesOutletColumns::searchableDbColumns();

        $query->where(function (Builder $query) use ($search, $searchColumns): void {
            foreach ($searchColumns as $column) {
                $query->orWhere($column, 'like', '%'.$search.'%');
            }
        });
    }

    private function applySort(Builder $query, string $sort, string $direction): void
    {
        $sortColumns = SalesOutletColumns::sortColumnMap();

        $query->orderBy($sortColumns[$sort] ?? 'id', $direction);
    }
}
