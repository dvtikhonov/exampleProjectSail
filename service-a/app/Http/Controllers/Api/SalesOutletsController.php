<?php

namespace App\Http\Controllers\Api;

use App\DTO\SalesOutlets\SalesOutletIndexQueryDto;
use App\DTO\SalesOutlets\UpdateHeadOrganizationDto;
use App\DTO\SalesOutlets\UpdateSalesOutletDto;
use App\Http\Controllers\Controller;
use App\Models\SalesOutlet;
use App\Services\SalesOutlets\SalesOutletServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SalesOutletsController extends Controller
{
    public function __construct(
        private readonly SalesOutletServiceInterface $salesOutletService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $queryDto = SalesOutletIndexQueryDto::fromRequest(
            request: $request,
            allowedColumns: $this->salesOutletService->allowedColumnKeys(),
        );

        return response()->json($this->salesOutletService->index($queryDto));
    }

    public function update(Request $request, SalesOutlet $salesOutlet): JsonResponse
    {
        $dto = UpdateSalesOutletDto::fromRequest($request);

        return response()->json(
            $this->salesOutletService->update($salesOutlet, $dto)->toArray(),
        );
    }

    public function updateHeadOrganization(Request $request, SalesOutlet $salesOutlet): JsonResponse
    {
        $dto = UpdateHeadOrganizationDto::fromRequest($request);

        return response()->json(
            $this->salesOutletService->updateHeadOrganization($salesOutlet, $dto)->toArray(),
        );
    }

    public function destroy(SalesOutlet $salesOutlet): JsonResponse
    {
        $this->salesOutletService->delete($salesOutlet);

        return response()->json(null, 204);
    }
}
