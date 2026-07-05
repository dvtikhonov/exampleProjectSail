<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Food;

use App\Contracts\Food\DishAdminServiceInterface;
use App\Contracts\Food\MenuCategoryRepositoryInterface;
use App\DTO\Food\AdminDishDto;
use App\Exceptions\Food\FoodDomainException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Food\Admin\ImportDishesSpreadsheetRequest;
use App\Http\Requests\Food\Admin\StoreDishRequest;
use App\Http\Requests\Food\Admin\UpdateDishRequest;
use App\Models\MenuCategory;
use App\Services\Food\DishSpreadsheetImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Административный CRUD блюд меню для MAX mini-app.
 */
class AdminDishController extends Controller
{
    public function __construct(
        private readonly DishAdminServiceInterface $dishAdminService,
        private readonly MenuCategoryRepositoryInterface $menuCategoryRepository,
        private readonly DishSpreadsheetImportService $dishSpreadsheetImportService,
    ) {}

    /**
     * Список категорий меню для select в админке.
     */
    public function menuCategories(Request $request): JsonResponse
    {
        $restaurantId = $this->optionalPositiveIntQuery($request, 'restaurant_id');
        $categories = $this->menuCategoryRepository->listForAdmin($restaurantId);

        return response()->json([
            'categories' => array_map(
                static fn (MenuCategory $category): array => [
                    'id' => $category->id,
                    'name' => $category->name,
                    'restaurant_id' => (int) $category->restaurant_id,
                    'restaurant_name' => (string) $category->restaurant?->name,
                ],
                $categories,
            ),
        ]);
    }

    /**
     * Список блюд для админки.
     */
    public function index(Request $request): JsonResponse
    {
        $restaurantId = $this->optionalPositiveIntQuery($request, 'restaurant_id');
        $categoryId = $this->optionalPositiveIntQuery($request, 'category_id');
        $nameSearch = $this->optionalTrimmedStringQuery($request, 'name', 255);

        $dishes = $this->dishAdminService->list($restaurantId, $categoryId, $nameSearch);

        return response()->json([
            'dishes' => array_map(
                static fn ($dish): array => $dish->toArray(),
                $dishes,
            ),
        ]);
    }

    /**
     * Карточка блюда для формы редактирования.
     */
    public function show(int $dish): JsonResponse
    {
        return $this->respondDish(function () use ($dish) {
            return $this->dishAdminService->show($dish);
        });
    }

    /**
     * Импорт блюд из XLS/XLSX (multipart/form-data).
     */
    public function import(ImportDishesSpreadsheetRequest $request): JsonResponse
    {
        try {
            $result = $this->dishSpreadsheetImportService->import(
                $request->spreadsheetFile(),
                $request->menuCategoryId(),
            );
        } catch (FoodDomainException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], $exception->statusCode());
        }

        if ($result->errors !== []) {
            return response()->json([
                'message' => 'Ошибки в файле импорта.',
                ...$result->toArray(),
            ], 422);
        }

        return response()->json($result->toArray());
    }

    /**
     * Создание блюда (multipart/form-data).
     */
    public function store(StoreDishRequest $request): JsonResponse
    {
        return $this->respondDish(function () use ($request) {
            return $this->dishAdminService->create(
                $request->toCreateDto(),
                $request->photo(),
            );
        }, 201);
    }

    /**
     * Обновление блюда (multipart/form-data, photo опционально).
     */
    public function update(UpdateDishRequest $request, int $dish): JsonResponse
    {
        return $this->respondDish(function () use ($request, $dish) {
            $existing = $this->dishAdminService->show($dish);

            return $this->dishAdminService->update(
                $dish,
                $request->toUpdateDtoFromExisting($existing),
                $request->photoOrNull(),
            );
        });
    }

    /**
     * Удаление блюда.
     */
    public function destroy(int $dish): Response
    {
        try {
            $this->dishAdminService->delete($dish);
        } catch (FoodDomainException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], $exception->statusCode());
        }

        return response()->noContent();
    }

    /**
     * @param  callable(): AdminDishDto  $action
     */
    private function respondDish(callable $action, int $status = 200): JsonResponse
    {
        try {
            $dish = $action();
        } catch (FoodDomainException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], $exception->statusCode());
        }

        return response()->json([
            'dish' => $dish->toArray(),
        ], $status);
    }

    private function optionalPositiveIntQuery(Request $request, string $key): ?int
    {
        $value = $request->query($key);

        if ($value === null || $value === '') {
            return null;
        }

        $intValue = (int) $value;

        return $intValue >= 1 ? $intValue : null;
    }

    private function optionalTrimmedStringQuery(Request $request, string $key, int $maxLength): ?string
    {
        $value = $request->query($key);

        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        if ($trimmed === '') {
            return null;
        }

        return mb_substr($trimmed, 0, $maxLength);
    }
}
