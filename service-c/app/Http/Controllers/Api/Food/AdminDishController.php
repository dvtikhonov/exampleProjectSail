<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Food;

use App\Contracts\Food\Menu\DishAdminServiceInterface;
use App\Contracts\Food\Menu\DishSpreadsheetImportServiceInterface;
use App\Contracts\Food\Menu\MenuAvailabilityDateResolverInterface;
use App\DTO\Food\Menu\AdminDishDto;
use App\Exceptions\Food\FoodDomainException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Food\Admin\ImportDishesSpreadsheetRequest;
use App\Http\Requests\Food\Admin\ListAdminDishesRequest;
use App\Http\Requests\Food\Admin\StoreDishRequest;
use App\Http\Requests\Food\Admin\UpdateDishRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Административный CRUD блюд меню для MAX mini-app.
 */
class AdminDishController extends Controller
{
    public function __construct(
        private readonly DishAdminServiceInterface $dishAdminService,
        private readonly DishSpreadsheetImportServiceInterface $dishSpreadsheetImportService,
        private readonly MenuAvailabilityDateResolverInterface $menuAvailabilityDateResolver,
    ) {}

    /**
     * Список блюд для админки.
     */
    public function index(ListAdminDishesRequest $request): JsonResponse
    {
        $dishes = $this->dishAdminService->list(
            $request->restaurantId(),
            $request->categoryId(),
            $request->nameSearch(),
            $request->availability(),
        );

        $menuAvailability = $this->menuAvailabilityDateResolver->resolve();

        return response()->json([
            'dishes' => array_map(
                static fn ($dish): array => $dish->toArray(),
                $dishes,
            ),
            'menu_availability_date' => $menuAvailability->date,
            'menu_availability_error' => $menuAvailability->error,
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

    /**
     * Создание блюда (multipart/form-data).
     */
    public function store(StoreDishRequest $request): JsonResponse
    {
        return $this->respondDish(function () use ($request) {
            return $this->dishAdminService->create(
                $request->toCreateDto(),
                $request->photoDto(),
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
                $request->photoDtoOrNull(),
            );
        });
    }

    /**
     * Удаление блюда.
     */
    public function destroy(int $dish): Response
    {
        $this->dishAdminService->delete($dish);

        return response()->noContent();
    }

    /**
     * @param  callable(): AdminDishDto  $action
     */
    private function respondDish(callable $action, int $status = 200): JsonResponse
    {
        try {
            $dish = $action();
        } catch (Throwable $exception) {
            if ($exception instanceof FoodDomainException) {
                throw $exception;
            }

            Log::error('Admin dish action failed.', [
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            return response()->json([
                'message' => 'Не удалось сохранить блюдо. Проверьте лог сервера (storage/logs/laravel.log).',
            ], 500);
        }

        return response()->json([
            'dish' => $dish->toArray(),
        ], $status);
    }
}
