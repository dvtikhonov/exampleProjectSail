<?php

namespace App\Http\Controllers\Api;

use App\DTO\SalesOutlets\SalesOutletExportJobDto;
use App\Http\Controllers\Controller;
use App\Http\Requests\SalesOutlets\StoreSalesOutletExportRequest;
use App\Services\SalesOutlets\SalesOutletsExportServiceInterface;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SalesOutletsExportController extends Controller
{
    public function __construct(
        private readonly SalesOutletsExportServiceInterface $exportService,
    ) {}

    public function store(StoreSalesOutletExportRequest $request): JsonResponse
    {
        $exportJob = $this->exportService->create(
            filters: $request->toDto(),
            userId: $request->user()?->id,
        );

        return response()->json(SalesOutletExportJobDto::fromModel($exportJob)->toArray(), Response::HTTP_ACCEPTED);
    }

    public function show(string $uuid): JsonResponse
    {
        $exportJob = $this->exportService->findByUuid($uuid);

        abort_if($exportJob === null, Response::HTTP_NOT_FOUND);

        return response()->json(SalesOutletExportJobDto::fromModel($exportJob)->toArray());
    }

    public function download(string $uuid): StreamedResponse|JsonResponse
    {
        $exportJob = $this->exportService->findByUuid($uuid);

        abort_if($exportJob === null, Response::HTTP_NOT_FOUND);

        if (! $this->exportService->isDownloadReady($exportJob)) {
            return response()->json(['message' => 'Export file is not ready.'], Response::HTTP_CONFLICT);
        }

        return $this->exportService->download($exportJob);
    }
}
