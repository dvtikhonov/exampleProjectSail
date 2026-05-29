<?php

namespace App\Http\Controllers\Api;

use App\DTO\SalesOutlets\SalesOutletMailJobDto;
use App\Http\Controllers\Controller;
use App\Http\Requests\SalesOutlets\StoreSalesOutletMailRequest;
use App\Services\SalesOutlets\SalesOutletsMailApiServiceInterface;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class SalesOutletsMailController extends Controller
{
    public function __construct(
        private readonly SalesOutletsMailApiServiceInterface $mailService,
    ) {}

    public function store(StoreSalesOutletMailRequest $request): JsonResponse
    {
        $mailJob = $this->mailService->create(
            filters: $request->toDto(),
            userId: $request->user()?->id,
        );

        return response()->json(SalesOutletMailJobDto::fromModel($mailJob)->toArray(), Response::HTTP_ACCEPTED);
    }

    public function show(string $uuid): JsonResponse
    {
        $mailJob = $this->mailService->findByUuid($uuid);

        abort_if($mailJob === null, Response::HTTP_NOT_FOUND);

        return response()->json(SalesOutletMailJobDto::fromModel($mailJob)->toArray());
    }
}
