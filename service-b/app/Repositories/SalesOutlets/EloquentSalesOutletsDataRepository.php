<?php

namespace App\Repositories\SalesOutlets;

use App\DTO\SalesOutlets\SalesOutletExportFilterDto;
use App\Enums\SalesOutletStatus;
use App\Models\SalesOutlet;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class EloquentSalesOutletsDataRepository implements SalesOutletsDataRepositoryInterface
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

    public function __construct(
        private readonly SalesOutletsExportMetadataRepositoryInterface $metadataRepository,
    ) {}

    public function exportRows(SalesOutletExportFilterDto $filters): Collection
    {
        $query = SalesOutlet::query();
        $allowedColumns = $this->metadataRepository->allowedColumnKeys();

        $this->applyStatus($query, $filters->status);
        $this->applyColumnFilters($query, $filters->columnFilters, $allowedColumns);
        $this->applySearch($query, $filters->search);
        $this->applySort($query, $filters->sort, $filters->direction);

        return $query->get()
            ->map(fn (SalesOutlet $salesOutlet): array => $this->row($salesOutlet));
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

    /**
     * @return array<string, int|string|null>
     */
    private function row(SalesOutlet $salesOutlet): array
    {
        return [
            'id' => $salesOutlet->id,
            'shop' => $salesOutlet->shop,
            'manager' => $salesOutlet->manager,
            'curator' => $salesOutlet->curator,
            'name' => $salesOutlet->name,
            'inn' => $salesOutlet->inn,
            'head_organization' => $salesOutlet->head_organization,
            'head_organization_type' => $salesOutlet->head_organization_type->value,
            'head_organization_type_label' => $salesOutlet->head_organization_type->label(),
            'organization_name' => $salesOutlet->organization_name,
            'status' => $salesOutlet->status->value,
            'status_label' => $salesOutlet->status->label(),
            'approved' => $salesOutlet->approved,
            'user_id' => $salesOutlet->user_id,
        ];
    }
}
