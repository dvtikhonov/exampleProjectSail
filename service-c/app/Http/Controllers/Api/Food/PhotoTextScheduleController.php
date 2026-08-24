<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Food;

use App\Contracts\Food\PhotoText\PhotoTextSchedulePlacementServiceInterface;
use App\Exceptions\Food\FoodDomainException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Food\PhotoText\PhotoTextScheduleSyncRequest;
use Illuminate\Http\JsonResponse;

/**
 * HTTP API агента Cursor: exact match и запись графика производства блюд.
 */
class PhotoTextScheduleController extends Controller
{
    public function __construct(
        private readonly PhotoTextSchedulePlacementServiceInterface $placementService,
    ) {}

    /**
     * Exact match имён в ресторане (±фильтр category_ids / category_id); график не пишется.
     */
    public function match(PhotoTextScheduleSyncRequest $request): JsonResponse
    {
        try {
            $result = $this->placementService->match(
                $request->restaurantId(),
                $request->categoryIds(),
                $request->dateFrom(),
                $request->dateTo(),
                $request->entries(),
            );
        } catch (FoodDomainException $exception) {
            return $this->domainError($exception);
        }

        return response()->json($result->toArray());
    }

    /**
     * Match + замена графика в окне только по указанным категориям (или всем, если scope пуст); пустой matched — 422.
     */
    public function apply(PhotoTextScheduleSyncRequest $request): JsonResponse
    {
        try {
            $result = $this->placementService->apply(
                $request->restaurantId(),
                $request->categoryIds(),
                $request->dateFrom(),
                $request->dateTo(),
                $request->entries(),
            );
        } catch (FoodDomainException $exception) {
            return $this->domainError($exception);
        }

        if (! $result->applied) {
            return response()->json($result->toArray(), JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        return response()->json($result->toArray());
    }

    /**
     * JSON-ответ с сообщением доменного исключения.
     */
    private function domainError(FoodDomainException $exception): JsonResponse
    {
        return response()->json([
            'message' => $exception->getMessage(),
        ], $exception->statusCode());
    }
}
