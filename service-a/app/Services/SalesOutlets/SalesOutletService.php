<?php

namespace App\Services\SalesOutlets;

use App\DTO\SalesOutlets\SalesOutletIndexQueryDto;
use App\DTO\SalesOutlets\UpdateHeadOrganizationDto;
use App\DTO\SalesOutlets\UpdateSalesOutletDto;
use App\Models\SalesOutlet;
use App\Repositories\SalesOutlets\SalesOutletRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Shared\SalesOutletsDomain\DTO\SalesOutletRowDto;
use Shared\SalesOutletsDomain\Enums\SalesOutletStatus;
use Shared\SalesOutletsDomain\Metadata\SalesOutletColumns;

class SalesOutletService implements SalesOutletServiceInterface
{
    /**
     * @var array<int, array<string, bool|int|string>>
     */
    private const COLUMN_UI = [
        'id' => ['width' => 120, 'align' => 'center'],
        'shop' => ['width' => 150, 'align' => 'center'],
        'manager' => ['width' => 190, 'align' => 'center'],
        'curator' => ['width' => 190, 'align' => 'center'],
        'name' => ['width' => 170, 'align' => 'center'],
        'inn' => ['width' => 170, 'align' => 'center'],
        'head_organization' => ['width' => 260, 'align' => 'center', 'cellType' => 'headOrganizationPoptip'],
        'head_organization_type' => ['width' => 120, 'align' => 'center'],
        'organization_name' => ['width' => 260, 'align' => 'center'],
        'status_label' => ['width' => 170, 'align' => 'center', 'cellType' => 'statusBadge'],
        'approved' => ['width' => 140, 'align' => 'center'],
        'user_id' => ['width' => 170, 'align' => 'center'],
    ];

    public function __construct(
        private readonly SalesOutletRepositoryInterface $salesOutletRepository,
    ) {}

    /**
     * @return array<int, array<string, bool|int|string>>
     */
    public function columns(): array
    {
        return array_map(
            fn (array $column): array => array_merge($column, self::COLUMN_UI[$column['key']] ?? []),
            SalesOutletColumns::all(),
        );
    }

    /**
     * @return array<int, string>
     */
    public function allowedColumnKeys(): array
    {
        return SalesOutletColumns::keys();
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
                'columns' => $this->columns(),
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
