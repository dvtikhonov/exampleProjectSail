<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Food;

use App\Contracts\Food\Menu\DishSpreadsheetImportServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\Food\Admin\ImportDishesSpreadsheetRequest;
use Illuminate\Http\JsonResponse;

/**
 * Импорт блюд из XLS/XLSX для админки MAX mini-app.
 */
class AdminDishImportController extends Controller
{
    public function __construct(
        private readonly DishSpreadsheetImportServiceInterface $dishSpreadsheetImportService,
    ) {}

    /**
     * Импорт блюд из XLS/XLSX (multipart/form-data).
     */
    public function import(ImportDishesSpreadsheetRequest $request): JsonResponse
    {
        $result = $this->dishSpreadsheetImportService->import(
            $request->spreadsheetFileDto(),
            $request->menuCategoryId(),
        );

        if ($result->errors !== []) {
            return response()->json([
                'message' => 'Ошибки в файле импорта.',
                ...$result->toArray(),
            ], 422);
        }

        return response()->json($result->toArray());
    }
}
