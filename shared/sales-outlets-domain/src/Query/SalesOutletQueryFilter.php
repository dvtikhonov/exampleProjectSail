<?php

namespace Shared\SalesOutletsDomain\Query;

use Illuminate\Database\Eloquent\Builder;
use Shared\SalesOutletsDomain\DTO\SalesOutletFilterDto;
use Shared\SalesOutletsDomain\Enums\SalesOutletStatus;
use Shared\SalesOutletsDomain\Query\SalesOutletFilterQuery\FilterQuerySalesOutletComposite;

final class SalesOutletQueryFilter
{
    protected FilterQuerySalesOutletComposite $filterQuerySalesOutletComposite;

    public function __construct()
    {
        $this->filterQuerySalesOutletComposite = app(FilterQuerySalesOutletComposite::class, []);
    }

    /**
     * Добавить фильтр
     *
     * @param $data
     * @param Closure $next
     * @return mixed
     */
    public function handle($data)
    {

        $this->filterQuerySalesOutletComposite->run($data);

        return $next($data);
    }

    /**
     * @var array<int, string>
     */
    private const SEARCH_COLUMNS = [
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
        'user_id',
    ];

    /**
     * @var array<string, string>
     */
    private const SORT_COLUMNS = [
        'id' => 'id',
        'shop' => 'shop',
        'manager' => 'manager',
        'curator' => 'curator',
        'name' => 'name',
        'inn' => 'inn',
        'head_organization' => 'head_organization',
        'head_organization_type' => 'head_organization_type',
        'organization_name' => 'organization_name',
        'status_label' => 'status',
        'approved' => 'approved',
        'user_id' => 'user_id',
    ];

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
        foreach ($columnFilters as $column => $value) {
            if (! in_array($column, $allowedColumnKeys, true)) {
                continue;
            }

            $this->whereLike($query, $column, $value);
        }
    }

    private function applySearch(Builder $query, string $search): void
    {
        if ($search === '') {
            return;
        }

        $query->where(function (Builder $query) use ($search): void {
            foreach (self::SEARCH_COLUMNS as $column) {
                $query->orWhere($column, 'like', '%'.$search.'%');
            }
        });
    }
    // todo надо доделать
    private function applySearch_2(Builder $query, string $search): void
    {
        $this->filterQuerySalesOutletComposite->run($query);

        if ($search === '') {
            return;
        }

        $query->where(function (Builder $query) use ($search): void {
            foreach (self::SEARCH_COLUMNS as $column) {
                $query->orWhere($column, 'like', '%'.$search.'%');
            }
        });
    }

    private function applySort(Builder $query, string $sort, string $direction): void
    {
        $query->orderBy(self::SORT_COLUMNS[$sort] ?? 'id', $direction);
    }

    private function whereLike(Builder $query, string $column, string $value): void
    {
        if ($column === 'status_label') {
            $query->whereIn('status', $this->statusesByLabel($value));

            return;
        }

        $query->where($column, 'like', '%'.$value.'%');
    }

    /**
     * @return array<int, string>
     */
    private function statusesByLabel(string $value): array
    {
        return array_values(array_map(
            fn (SalesOutletStatus $status): string => $status->value,
            array_filter(
                SalesOutletStatus::cases(),
                fn (SalesOutletStatus $status): bool => str_contains(
                    mb_strtolower($status->label()),
                    mb_strtolower($value),
                ),
            ),
        ));
    }
}
