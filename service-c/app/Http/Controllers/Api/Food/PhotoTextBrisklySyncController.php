<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Food;

use App\Contracts\Food\BrisklySync\BrisklySyncSourceCollectorInterface;
use App\DTO\Food\BrisklySync\SourceMenuLineDto;
use App\Http\Controllers\Controller;
use App\Http\Requests\Food\PhotoText\PhotoTextBrisklySourceLinesRequest;
use Illuminate\Http\JsonResponse;

/**
 * PhotoText API: source-линии VPS для удалённого Briskly sync (агент + AI-доступ).
 */
class PhotoTextBrisklySyncController extends Controller
{
    public function __construct(
        private readonly BrisklySyncSourceCollectorInterface $sourceCollector,
    ) {}

    /**
     * Список source-линий выбранного ресторана с фильтрами категории и текста.
     */
    public function sourceLines(PhotoTextBrisklySourceLinesRequest $request): JsonResponse
    {
        $lines = $this->sourceCollector->collectForRestaurant(
            $request->restaurantId(),
            $request->vpsCategoryId(),
            $request->searchText(),
        );

        return response()->json([
            'source_lines' => array_map(
                static fn (SourceMenuLineDto $line): array => $line->toArray(),
                $lines,
            ),
        ]);
    }
}
