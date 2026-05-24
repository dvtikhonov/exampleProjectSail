<?php

namespace App\Services\SalesOutlets;

use App\DTO\SalesOutlets\SalesOutletIndexQueryDto;
use App\DTO\SalesOutlets\SalesOutletRowDto;
use App\DTO\SalesOutlets\UpdateHeadOrganizationDto;
use App\DTO\SalesOutlets\UpdateSalesOutletDto;
use App\Enums\SalesOutletStatus;
use App\Models\SalesOutlet;
use App\Repositories\SalesOutlets\SalesOutletRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class SalesOutletService implements SalesOutletServiceInterface
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
        ['key' => 'user_id', 'label' => 'Последний пользователь', 'sortable' => true, 'width' => 170, 'align' => 'center'],
    ];

    public function __construct(
        private readonly SalesOutletRepositoryInterface $salesOutletRepository,
    ) {}

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
        $paginator = $this->salesOutletRepository->paginate($queryDto, $this->allowedColumnKeys());

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
        $salesOutlet = $this->salesOutletRepository->updateHeadOrganization($salesOutlet, $dto);

        return SalesOutletRowDto::fromModel($salesOutlet);
    }

    public function update(SalesOutlet $salesOutlet, UpdateSalesOutletDto $dto): SalesOutletRowDto
    {
        $salesOutlet = $this->salesOutletRepository->update($salesOutlet, $dto);

        return SalesOutletRowDto::fromModel($salesOutlet);
    }

    public function delete(SalesOutlet $salesOutlet): void
    {
        $this->salesOutletRepository->delete($salesOutlet);
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
