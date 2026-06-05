<?php

namespace App\Http\Controllers\Api;

use App\Contracts\Repositories\SalesOutlets\SalesOutletsMetadataRepositoryInterface;
use App\Contracts\SalesOutlets\SalesOutletServiceInterface;
use App\Contracts\SalesOutlets\SalesOutletTableMetaProviderInterface;
use App\Domain\SalesOutlets\SalesOutlet;
use App\Http\Controllers\Controller;
use App\Http\Requests\SalesOutlets\IndexSalesOutletsRequest;
use App\Http\Requests\SalesOutlets\UpdateHeadOrganizationRequest;
use App\Http\Requests\SalesOutlets\UpdateSalesOutletRequest;
use App\Http\Responses\SalesOutletIndexResponse;
use App\Presentation\SalesOutlets\SalesOutletRowPresenter;
use Illuminate\Http\JsonResponse;

class SalesOutletsController extends Controller
{
    public function __construct(
        private readonly SalesOutletServiceInterface $salesOutletService,
        private readonly SalesOutletsMetadataRepositoryInterface $metadataRepository,
        private readonly SalesOutletTableMetaProviderInterface $tableMetaProvider,
    ) {}

    public function index(IndexSalesOutletsRequest $request): JsonResponse
    {
        $queryDto = $request->toQueryDto($this->metadataRepository);
        $result = $this->salesOutletService->index($queryDto);

        return response()->json(
            SalesOutletIndexResponse::from($result, $this->tableMetaProvider),
        );
    }

    public function update(UpdateSalesOutletRequest $request, SalesOutlet $salesOutlet): JsonResponse
    {
        return response()->json(
            SalesOutletRowPresenter::fromDomain($this->salesOutletService->update($salesOutlet, $request->toDto()))->toArray(),
        );
    }

    public function updateHeadOrganization(UpdateHeadOrganizationRequest $request, SalesOutlet $salesOutlet): JsonResponse
    {
        return response()->json(
            SalesOutletRowPresenter::fromDomain($this->salesOutletService->updateHeadOrganization($salesOutlet, $request->toDto()))->toArray(),
        );
    }

    public function destroy(SalesOutlet $salesOutlet): JsonResponse
    {
        $this->salesOutletService->delete($salesOutlet);

        return response()->json(null, 204);
    }
}
