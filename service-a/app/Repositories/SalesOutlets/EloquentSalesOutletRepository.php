<?php

namespace App\Repositories\SalesOutlets;

use App\Contracts\Auth\GatewayUserContextInterface;
use App\Contracts\Repositories\SalesOutlets\SalesOutletRepositoryInterface;
use App\Contracts\Repositories\SalesOutlets\SalesOutletsMetadataRepositoryInterface;
use App\Domain\SalesOutlets\SalesOutlet;
use App\DTO\SalesOutlets\SalesOutletIndexQueryDto;
use App\DTO\SalesOutlets\UpdateHeadOrganizationDto;
use App\DTO\SalesOutlets\UpdateSalesOutletDto;
use App\Models\SalesOutlet as SalesOutletModel;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Shared\SalesOutletsDomain\DTO\SalesOutletFilterDto;
use Shared\SalesOutletsDomain\Query\SalesOutletQueryFilter;

class EloquentSalesOutletRepository implements SalesOutletRepositoryInterface
{
    public function __construct(
        private readonly SalesOutletsMetadataRepositoryInterface $metadataRepository,
        private readonly SalesOutletQueryFilter $queryFilter,
        private readonly GatewayUserContextInterface $gatewayUserContext,
    ) {}

    public function findById(int $id): ?SalesOutlet
    {
        $model = SalesOutletModel::query()->find($id);

        return $model !== null ? SalesOutletModelMapper::toDomain($model) : null;
    }

    public function paginate(SalesOutletIndexQueryDto $queryDto): LengthAwarePaginator
    {
        $query = SalesOutletModel::query();

        $this->queryFilter->apply(
            query: $query,
            filters: new SalesOutletFilterDto(
                search: $queryDto->search,
                status: $queryDto->status,
                columnFilters: $queryDto->columnFilters,
                sort: $queryDto->sort,
                direction: $queryDto->direction,
            ),
            allowedColumnKeys: $this->metadataRepository->allowedColumnKeys(),
        );

        return $query->paginate(
            perPage: $queryDto->perPage,
            columns: ['*'],
            pageName: 'page',
            page: $queryDto->page,
        )->through(fn (SalesOutletModel $model): SalesOutlet => SalesOutletModelMapper::toDomain($model));
    }

    public function updateHeadOrganization(SalesOutlet $salesOutlet, UpdateHeadOrganizationDto $dto): SalesOutlet
    {
        return $this->persist($salesOutlet, [
            'head_organization' => $dto->headOrganization,
            'head_organization_type' => $dto->headOrganizationType,
        ]);
    }

    public function update(SalesOutlet $salesOutlet, UpdateSalesOutletDto $dto): SalesOutlet
    {
        return $this->persist($salesOutlet, [
            'shop' => $dto->shop,
            'manager' => $dto->manager,
            'curator' => $dto->curator,
            'name' => $dto->name,
            'inn' => $dto->inn,
            'head_organization' => $dto->headOrganization,
            'head_organization_type' => $dto->headOrganizationType,
            'organization_name' => $dto->organizationName,
            'status' => $dto->status,
        ]);
    }

    public function delete(SalesOutlet $salesOutlet): void
    {
        $model = $this->resolveModel($salesOutlet);
        $userId = $this->gatewayUserContext->currentUserId();

        if ($userId !== null) {
            $model->forceFill(['user_id' => $userId])->saveQuietly();
        }

        $model->delete();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function persist(SalesOutlet $salesOutlet, array $attributes): SalesOutlet
    {
        $model = $this->resolveModel($salesOutlet);
        $model->forceFill($attributes);
        $this->applyGatewayUserId($model);
        $model->save();

        return SalesOutletModelMapper::toDomain($model->refresh());
    }

    private function resolveModel(SalesOutlet $salesOutlet): SalesOutletModel
    {
        return SalesOutletModel::query()->findOrFail($salesOutlet->id);
    }

    private function applyGatewayUserId(SalesOutletModel $salesOutlet): void
    {
        $userId = $this->gatewayUserContext->currentUserId();

        if ($userId === null) {
            return;
        }

        $salesOutlet->forceFill([
            'user_id' => $userId,
        ]);
    }
}
