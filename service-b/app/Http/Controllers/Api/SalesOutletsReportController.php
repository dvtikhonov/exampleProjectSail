<?php

namespace App\Http\Controllers\Api;

use App\Contracts\SalesOutlets\SalesOutletReportFilterDtoFactoryInterface;
use App\Contracts\SalesOutlets\SalesOutletsReportApiServiceInterface;
use App\Contracts\SalesOutlets\SalesOutletsReportDownloadServiceInterface;
use App\DTO\SalesOutlets\SalesOutletReportJobDto;
use App\Http\Controllers\Controller;
use App\Http\Requests\SalesOutlets\StoreSalesOutletReportRequest;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SalesOutletsReportController extends Controller
{
    public function __construct(
        private readonly SalesOutletsReportApiServiceInterface $reportService,
        private readonly SalesOutletsReportDownloadServiceInterface $downloadService,
        private readonly SalesOutletReportFilterDtoFactoryInterface $filterDtoFactory,
    ) {}

    public function store(StoreSalesOutletReportRequest $request): JsonResponse
    {
        $reportJob = $this->reportService->create(
            filters: $this->filterDtoFactory->fromValidated($request->validated()),
            userId: $request->user()?->id,
            reportType: $request->toReportType(),
        );

        return response()->json(
            SalesOutletReportJobDto::fromAsyncJob($reportJob)->toArray(),
            Response::HTTP_ACCEPTED,
        );
    }

    public function show(string $uuid): JsonResponse
    {
        $reportJob = $this->reportService->findByUuid($uuid);

        abort_if($reportJob === null, Response::HTTP_NOT_FOUND);

        return response()->json(SalesOutletReportJobDto::fromAsyncJob($reportJob)->toArray());
    }

    public function download(string $uuid): StreamedResponse|JsonResponse
    {
        $reportJob = $this->reportService->findByUuid($uuid);

        abort_if($reportJob === null, Response::HTTP_NOT_FOUND);

        if (! $this->downloadService->supportsDownload($reportJob)) {
            abort(Response::HTTP_NOT_FOUND);
        }

        if (! $this->downloadService->isDownloadReady($reportJob)) {
            return response()->json(['message' => 'Report file is not ready.'], Response::HTTP_CONFLICT);
        }

        return $this->downloadService->download($reportJob);
    }
}
