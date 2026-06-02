<?php

namespace App\Http\Controllers\Api;

use App\Contracts\Repositories\SalesOutlets\SalesOutletsReportStatsRepositoryInterface;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class SalesOutletsReportStatsController extends Controller
{
    public function __construct(
        private readonly SalesOutletsReportStatsRepositoryInterface $statsRepository,
    ) {}

    public function index(): JsonResponse
    {
        return response()->json(
            $this->statsRepository->aggregate()->toArray(),
        );
    }
}
