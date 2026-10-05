<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Food;

use App\Contracts\Food\BrisklySync\BrisklySyncVpsCatalogPortInterface;
use App\DTO\Food\BrisklySync\BrisklySyncVpsNamedItemDto;
use App\DTO\Food\BrisklySync\SourceMenuLineDto;
use App\Http\Controllers\Controller;
use App\Http\Requests\Food\Admin\ListBrisklySyncSourceLinesRequest;
use App\Http\Requests\Food\Admin\ListBrisklySyncVpsCategoriesRequest;
use Illuminate\Http\JsonResponse;

/**
 * Admin API: source-каталог VPS для модуля синхронизации Briskly (роль max_manager).
 *
 * Рестораны / категории / source-линии читаются через порт (local или remote PhotoText).
 */
class AdminBrisklySyncSourceController extends Controller
{
    public function __construct(
        private readonly BrisklySyncVpsCatalogPortInterface $vpsCatalog,
    ) {}

    /**
     * Активные рестораны source-каталога (id, name).
     */
    public function restaurants(): JsonResponse
    {
        $restaurants = $this->vpsCatalog->listRestaurants();

        return response()->json([
            'restaurants' => array_map(
                static fn (BrisklySyncVpsNamedItemDto $item): array => $item->toArray(),
                $restaurants,
            ),
        ]);
    }

    /**
     * Категории меню выбранного ресторана (id, name).
     */
    public function vpsCategories(ListBrisklySyncVpsCategoriesRequest $request): JsonResponse
    {
        $restaurantId = $request->restaurantId();
        $categories = $this->vpsCatalog->listVpsCategories($restaurantId);

        return response()->json([
            'categories' => array_map(
                static fn (BrisklySyncVpsNamedItemDto $item): array => $item->toArray(),
                $categories,
            ),
        ]);
    }

    /**
     * Список source-линий выбранного ресторана с фильтрами категории и текста.
     */
    public function sourceLines(ListBrisklySyncSourceLinesRequest $request): JsonResponse
    {
        $restaurantId = $request->restaurantId();
        $vpsCategoryId = $request->vpsCategoryId();

        $this->vpsCatalog->assertRestaurantActive($restaurantId);
        $this->vpsCatalog->assertCategoryBelongs($restaurantId, $vpsCategoryId);

        $lines = $this->vpsCatalog->collectSourceLines(
            $restaurantId,
            $vpsCategoryId,
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
