<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Food;

use App\Contracts\Food\Menu\MenuCategoryAdminServiceInterface;
use App\DTO\Food\Menu\AdminMenuCategoryDto;
use App\Http\Controllers\Controller;
use App\Http\Requests\Food\Admin\ListAdminMenuCategoriesRequest;
use App\Http\Requests\Food\Admin\StoreMenuCategoryRequest;
use App\Http\Requests\Food\Admin\UpdateMenuCategoryRequest;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Административный CRUD категорий меню для MAX mini-app.
 */
class AdminMenuCategoryController extends Controller
{
    public function __construct(
        private readonly MenuCategoryAdminServiceInterface $menuCategoryAdminService,
    ) {}

    /**
     * Список категорий меню.
     */
    public function index(ListAdminMenuCategoriesRequest $request): JsonResponse
    {
        $categories = $this->menuCategoryAdminService->list($request->restaurantId());

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
            return $this->menuCategoryAdminService->create(
                $request->toCreateDto(),
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
        $this->menuCategoryAdminService->delete($menuCategory);

        return response()->noContent();
    }

    /**
     * @param  callable(): AdminMenuCategoryDto  $action
     */
    private function respondCategory(callable $action, int $status = 200): JsonResponse
    {
        $category = $action();

        return response()->json([
            'category' => $category->toArray(),
        ], $status);
    }
}
