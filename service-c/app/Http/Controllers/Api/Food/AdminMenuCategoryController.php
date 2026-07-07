<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Food;

use App\Contracts\Food\MenuCategoryAdminServiceInterface;
use App\Contracts\Food\MenuCategoryRepositoryInterface;
use App\Exceptions\Food\FoodDomainException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Food\Admin\StoreMenuCategoryRequest;
use App\Http\Requests\Food\Admin\UpdateMenuCategoryRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Административный CRUD категорий меню для MAX mini-app.
 */
class AdminMenuCategoryController extends Controller
{
    public function __construct(
        private readonly MenuCategoryAdminServiceInterface $menuCategoryAdminService,
        private readonly MenuCategoryRepositoryInterface $menuCategoryRepository,
    ) {}

    /**
     * Список категорий меню.
     */
    public function index(Request $request): JsonResponse
    {
        $restaurantId = $this->optionalPositiveIntQuery($request, 'restaurant_id');
        $categories = $this->menuCategoryAdminService->list($restaurantId);

        return response()->json([
            'categories' => array_map(
                static fn ($category): array => $category->toArray(),
                $categories,
            ),
        ]);
    }

    /**
     * Карточка категории для формы редактирования.
     */
    public function show(int $menuCategory): JsonResponse
    {
        return $this->respondCategory(function () use ($menuCategory) {
            return $this->menuCategoryAdminService->show($menuCategory);
        });
    }

    /**
     * Создание категории меню.
     */
    public function store(StoreMenuCategoryRequest $request): JsonResponse
    {
        return $this->respondCategory(function () use ($request) {
            $restaurantId = (int) $request->validated('restaurant_id');
            $defaultSortOrder = $this->menuCategoryRepository->nextSortOrderForRestaurant($restaurantId);

            return $this->menuCategoryAdminService->create(
                $request->toCreateDto($defaultSortOrder),
            );
        }, 201);
    }

    /**
     * Обновление категории меню.
     */
    public function update(UpdateMenuCategoryRequest $request, int $menuCategory): JsonResponse
    {
        return $this->respondCategory(function () use ($request, $menuCategory) {
            return $this->menuCategoryAdminService->update(
                $menuCategory,
                $request->toUpdateDto(),
            );
        });
    }

    /**
     * Удаление категории меню.
     */
    public function destroy(int $menuCategory): Response
    {
        try {
            $this->menuCategoryAdminService->delete($menuCategory);
        } catch (FoodDomainException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], $exception->statusCode());
        }

        return response()->noContent();
    }

    /**
     * @param  callable(): \App\DTO\Food\AdminMenuCategoryDto  $action
     */
    private function respondCategory(callable $action, int $status = 200): JsonResponse
    {
        try {
            $category = $action();
        } catch (FoodDomainException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], $exception->statusCode());
        }

        return response()->json([
            'category' => $category->toArray(),
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
}
