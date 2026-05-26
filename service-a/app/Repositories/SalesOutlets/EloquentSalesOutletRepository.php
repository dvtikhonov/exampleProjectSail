<?php

namespace App\Repositories\SalesOutlets;

use App\DTO\SalesOutlets\SalesOutletIndexQueryDto;
use App\DTO\SalesOutlets\UpdateHeadOrganizationDto;
use App\DTO\SalesOutlets\UpdateSalesOutletDto;
use App\Models\SalesOutlet;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Shared\SalesOutletsDomain\DTO\SalesOutletFilterDto;
// todo временно отключили Shared
use Shared\SalesOutletsDomain\Query\SalesOutletQueryFilter;
//use App\QueryDebug\SalesOutletQueryFilter;

class EloquentSalesOutletRepository implements SalesOutletRepositoryInterface
{
    public function __construct(
        private readonly SalesOutletQueryFilter $queryFilter = new SalesOutletQueryFilter(),
    ) {}

    /**
     * @param  array<int, string>  $allowedColumnKeys
     */
    public function paginate(SalesOutletIndexQueryDto $queryDto, array $allowedColumnKeys): LengthAwarePaginator
    {
        $query = SalesOutlet::query();

        $this->queryFilter->apply(
            query: $query,
            filters: new SalesOutletFilterDto(
                search: $queryDto->search,
                status: $queryDto->status,
                columnFilters: $queryDto->columnFilters,
                sort: $queryDto->sort,
                direction: $queryDto->direction,
            ),
            allowedColumnKeys: $allowedColumnKeys,
        );

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

    public function update(SalesOutlet $salesOutlet, UpdateSalesOutletDto $dto): SalesOutlet
    {
        $salesOutlet->forceFill([
            'shop' => $dto->shop,
            'manager' => $dto->manager,
            'curator' => $dto->curator,
            'name' => $dto->name,
            'inn' => $dto->inn,
            'head_organization' => $dto->headOrganization,
            'head_organization_type' => $dto->headOrganizationType,
            'organization_name' => $dto->organizationName,
            'status' => $dto->status,
        ])->save();

        return $salesOutlet->refresh();
    }

    public function delete(SalesOutlet $salesOutlet): void
    {
        $salesOutlet->delete();
    }
}
