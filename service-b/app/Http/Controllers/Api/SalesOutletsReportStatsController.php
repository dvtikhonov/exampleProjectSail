<?php

namespace App\Http\Controllers\Api;

use App\Contracts\SalesOutlets\SalesOutletsReportStatsServiceInterface;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class SalesOutletsReportStatsController extends Controller
{
    public function __construct(
        private readonly SalesOutletsReportStatsServiceInterface $statsService,
    ) {}

    public function index(): JsonResponse
    {
        return response()->json(
            $this->statsService->aggregate()->toArray(),
        );
    }
}
