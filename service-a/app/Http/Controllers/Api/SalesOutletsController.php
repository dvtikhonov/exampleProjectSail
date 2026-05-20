<?php

namespace App\Http\Controllers\Api;

use App\DTO\SalesOutlets\SalesOutletIndexQueryDto;
use App\DTO\SalesOutlets\UpdateHeadOrganizationDto;
use App\Http\Controllers\Controller;
use App\Models\SalesOutlet;
use App\Services\SalesOutlets\SalesOutletService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SalesOutletsController extends Controller
{
    public function __construct(
        private readonly SalesOutletService $salesOutletService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $queryDto = SalesOutletIndexQueryDto::fromRequest(
            request: $request,
            allowedColumns: $this->salesOutletService->allowedColumnKeys(),
        );

        return response()->json($this->salesOutletService->index($queryDto));
    }

    public function updateHeadOrganization(Request $request, SalesOutlet $salesOutlet): JsonResponse
    {
        $dto = UpdateHeadOrganizationDto::fromRequest($request);

        return response()->json(
            $this->salesOutletService->updateHeadOrganization($salesOutlet, $dto)->toArray(),
        );
    }
}
