<?php

namespace App\Services\SalesOutlets;

use App\DTO\SalesOutlets\SalesOutletIndexQueryDto;
use App\DTO\SalesOutlets\SalesOutletRowDto;
use App\DTO\SalesOutlets\UpdateHeadOrganizationDto;
use App\Enums\SalesOutletStatus;
use App\Models\SalesOutlet;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class SalesOutletService
{
    /**
     * @var array<int, array<string, bool|int|string>>
     */
    private const COLUMNS = [
        ['key' => 'id', 'label' => 'ID объекта продаж', 'sortable' => true, 'width' => 120, 'align' => 'center'],
        ['key' => 'shop', 'label' => 'Магазин', 'sortable' => true, 'width' => 150, 'align' => 'center'],
        ['key' => 'manager', 'label' => 'Менеджер', 'sortable' => true, 'width' => 190, 'align' => 'center'],
        ['key' => 'curator', 'label' => 'Куратор ТТ', 'sortable' => true, 'width' => 190, 'align' => 'center'],
        ['key' => 'name', 'label' => 'Название ТТ', 'sortable' => true, 'width' => 170, 'align' => 'center'],
        ['key' => 'inn', 'label' => 'ИНН головной', 'sortable' => true, 'width' => 170, 'align' => 'center'],
        ['key' => 'head_organization', 'label' => 'Головная организация', 'sortable' => true, 'width' => 260, 'align' => 'center', 'cellType' => 'headOrganizationPoptip'],
        ['key' => 'head_organization_type', 'label' => 'Вид', 'sortable' => true, 'width' => 120, 'align' => 'center'],
        ['key' => 'organization_name', 'label' => 'Название организации', 'sortable' => true, 'width' => 260, 'align' => 'center'],
        ['key' => 'status_label', 'label' => 'Статус', 'sortable' => true, 'width' => 170, 'align' => 'center', 'cellType' => 'statusBadge'],
        ['key' => 'approved', 'label' => 'Одобрено', 'sortable' => true, 'width' => 140, 'align' => 'center'],
    ];

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
    ];

    /**
     * @return array<int, array<string, bool|int|string>>
     */
    public function columns(): array
    {
        return self::COLUMNS;
    }

    /**
     * @return array<int, string>
     */
    public function allowedColumnKeys(): array
    {
        return array_column(self::COLUMNS, 'key');
    }

    /**
     * @return array<string, mixed>
     */
    public function index(SalesOutletIndexQueryDto $queryDto): array
    {
        $query = SalesOutlet::query();

        $this->applyStatus($query, $queryDto->status);
        $this->applyColumnFilters($query, $queryDto->columnFilters);
        $this->applySearch($query, $queryDto->search);
        $this->applySort($query, $queryDto->sort, $queryDto->direction);

        $paginator = $query->paginate(
            perPage: $queryDto->perPage,
            columns: ['*'],
            pageName: 'page',
            page: $queryDto->page,
        );

        return [
            'data' => $paginator->getCollection()
                ->map(fn (SalesOutlet $salesOutlet): array => SalesOutletRowDto::fromModel($salesOutlet)->toArray())
                ->values()
                ->all(),
            'meta' => [
                'columns' => self::COLUMNS,
                'filters' => [
                    'search' => $queryDto->search,
                    'status' => $queryDto->status,
                    'column_filters' => $queryDto->columnFilters,
                    'sort' => $queryDto->sort,
                    'direction' => $queryDto->direction,
                    'page' => $paginator->currentPage(),
                    'per_page' => $queryDto->perPage,
                    'columns' => $queryDto->columns,
                ],
                'pagination' => $this->pagination($paginator),
                'status_options' => array_merge(
                    [['value' => '', 'label' => 'Все статусы']],
                    SalesOutletStatus::options(),
                ),
            ],
        ];
    }

    public function updateHeadOrganization(SalesOutlet $salesOutlet, UpdateHeadOrganizationDto $dto): SalesOutletRowDto
    {
        $salesOutlet->forceFill([
            'head_organization' => $dto->headOrganization,
            'head_organization_type' => $dto->headOrganizationType,
        ])->save();

        return SalesOutletRowDto::fromModel($salesOutlet->refresh());
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
     */
    private function applyColumnFilters(Builder $query, array $columnFilters): void
    {
        foreach ($columnFilters as $column => $value) {
            if (! in_array($column, $this->allowedColumnKeys(), true)) {
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

    /**
     * @return array<string, int>
     */
    private function pagination(LengthAwarePaginator $paginator): array
    {
        return [
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'from' => $paginator->firstItem() ?? 0,
            'to' => $paginator->lastItem() ?? 0,
        ];
    }
}
