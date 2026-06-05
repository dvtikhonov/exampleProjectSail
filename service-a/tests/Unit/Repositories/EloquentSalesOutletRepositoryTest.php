<?php

namespace Tests\Unit\Repositories;

use App\Contracts\Auth\GatewayUserContextInterface;
use App\Contracts\Repositories\SalesOutlets\SalesOutletsMetadataRepositoryInterface;
use App\DTO\SalesOutlets\UpdateHeadOrganizationDto;
use App\Models\SalesOutlet as SalesOutletModel;
use App\Repositories\SalesOutlets\EloquentSalesOutletRepository;
use App\Repositories\SalesOutlets\SalesOutletModelMapper;
use Database\Seeders\SalesOutletSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Shared\SalesOutletsDomain\Enums\HeadOrganizationType;
use Shared\SalesOutletsDomain\Query\SalesOutletQueryFilter;
use Tests\TestCase;

class EloquentSalesOutletRepositoryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(SalesOutletSeeder::class);
    }

    public function test_it_stores_gateway_user_id_on_update(): void
    {
        $userId = 54321;
        $repository = $this->makeRepository($userId);
        $salesOutlet = SalesOutletModelMapper::toDomain(SalesOutletModel::query()->findOrFail(1001));
        $dto = new UpdateHeadOrganizationDto(
            headOrganization: 'Repo org',
            headOrganizationType: HeadOrganizationType::JointStockCompany,
        );

        $repository->updateHeadOrganization($salesOutlet, $dto);

        $this->assertDatabaseHas('sales_outlets', [
            'id' => 1001,
            'head_organization' => 'Repo org',
            'head_organization_type' => 'ao',
            'user_id' => $userId,
        ]);
    }

    public function test_it_stores_gateway_user_id_on_soft_delete(): void
    {
        $userId = 54321;
        $repository = $this->makeRepository($userId);
        $salesOutlet = SalesOutletModelMapper::toDomain(SalesOutletModel::query()->findOrFail(1001));

        $repository->delete($salesOutlet);

        $this->assertSoftDeleted('sales_outlets', [
            'id' => 1001,
            'user_id' => $userId,
        ]);
    }

    private function makeRepository(?int $userId): EloquentSalesOutletRepository
    {
        $metadataRepository = $this->createMock(SalesOutletsMetadataRepositoryInterface::class);
        $metadataRepository->method('allowedColumnKeys')->willReturn([]);

        $gatewayUserContext = $this->createMock(GatewayUserContextInterface::class);
        $gatewayUserContext->method('currentUserId')->willReturn($userId);

        return new EloquentSalesOutletRepository(
            metadataRepository: $metadataRepository,
            queryFilter: new SalesOutletQueryFilter,
            gatewayUserContext: $gatewayUserContext,
        );
    }
}
