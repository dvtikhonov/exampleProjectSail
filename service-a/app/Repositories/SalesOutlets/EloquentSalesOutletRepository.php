<?php

namespace App\Repositories\SalesOutlets;

use App\DTO\SalesOutlets\SalesOutletIndexQueryDto;
use App\DTO\SalesOutlets\UpdateHeadOrganizationDto;
use App\Enums\SalesOutletStatus;
use App\Models\SalesOutlet;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class EloquentSalesOutletRepository implements SalesOutletRepositoryInterface
{
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
    public function paginate(SalesOutletIndexQueryDto $queryDto, array $allowedColumnKeys): LengthAwarePaginator
    {
        $query = SalesOutlet::query();

        $this->applyStatus($query, $queryDto->status);
        $this->applyColumnFilters($query, $queryDto->columnFilters, $allowedColumnKeys);
        $this->applySearch($query, $queryDto->search);
        $this->applySort($query, $queryDto->sort, $queryDto->direction);

        return $query->paginate(
            perPage: $queryDto->perPage,
            columns: ['*'],
            pageName: 'page',
            page: $queryDto->page,
        );
    }

    public function updateHeadOrganization(SalesOutlet $salesOutlet, UpdateHeadOrganizationDto $dto): SalesOutlet
    {
        $salesOutlet->forceFill([
            'head_organization' => $dto->headOrganization,
            'head_organization_type' => $dto->headOrganizationType,
        ])->save();

        return $salesOutlet->refresh();
    }

    public function delete(SalesOutlet $salesOutlet): void
    {
        $salesOutlet->delete();
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
                $this->orWhereLike($query, $column, $search);
            }
        });
    }

    private function applySort(Builder $query, string $sort, string $direction): void
    {
        $sortColumn = self::SORT_COLUMNS[$sort] ?? 'id';

        $query->orderBy($sortColumn, $direction);
    }

    private function whereLike(Builder $query, string $column, string $value): void
    {
        if ($column === 'status_label') {
            $query->whereIn('status', $this->statusesByLabel($value));

            return;
        }

        $query->where($column, 'like', '%'.$value.'%');
    }

    private function orWhereLike(Builder $query, string $column, string $value): void
    {
        $query->orWhere($column, 'like', '%'.$value.'%');
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
