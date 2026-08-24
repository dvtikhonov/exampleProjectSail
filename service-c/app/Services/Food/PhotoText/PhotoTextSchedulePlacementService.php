<?php

declare(strict_types=1);

namespace App\Services\Food\PhotoText;

use App\Contracts\Food\Menu\DishAvailabilityRepositoryInterface;
use App\Contracts\Food\Menu\DishAvailabilityScheduleServiceInterface;
use App\Contracts\Food\Menu\DishCatalogRepositoryInterface;
use App\Contracts\Food\Menu\MenuCategoryRepositoryInterface;
use App\Contracts\Food\PhotoText\PhotoTextSchedulePlacementServiceInterface;
use App\DTO\Food\Menu\DishAvailabilityChangeDto;
use App\DTO\Food\Menu\DishAvailabilityUpdateDto;
use App\DTO\Food\PhotoText\PhotoTextScheduleEntryDto;
use App\DTO\Food\PhotoText\PhotoTextScheduleIssueDto;
use App\DTO\Food\PhotoText\PhotoTextScheduleMatchedDto;
use App\DTO\Food\PhotoText\PhotoTextScheduleResultDto;
use App\Enums\Food\PhotoText\PhotoTextMatchIssueCode;
use App\Exceptions\Food\FoodDomainException;
use App\Models\Food\Dish;
use App\Models\Food\MenuCategory;

/**
 * Exact match имён графика PhotoText и запись через DishAvailabilityScheduleService.
 * Apply заменяет график в окне целиком (фото — источник истины): старые даты в scope удаляются.
 */
class PhotoTextSchedulePlacementService implements PhotoTextSchedulePlacementServiceInterface
{
    public function __construct(
        private readonly DishCatalogRepositoryInterface $dishRepository,
        private readonly MenuCategoryRepositoryInterface $menuCategoryRepository,
        private readonly DishAvailabilityRepositoryInterface $availabilityRepository,
        private readonly DishAvailabilityScheduleServiceInterface $scheduleService,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function match(
        int $restaurantId,
        ?array $categoryIds,
        string $dateFrom,
        string $dateTo,
        array $entries,
    ): PhotoTextScheduleResultDto {
        $scopeCategoryIds = $this->normalizeCategoryIds($categoryIds);
        $this->assertOptionalCategoriesBelongToRestaurant($scopeCategoryIds, $restaurantId);

        $matched = [];
        $issues = [];

        foreach ($entries as $entry) {
            $resolved = $this->resolveEntry($entry, $restaurantId, $scopeCategoryIds);

            if ($resolved instanceof PhotoTextScheduleMatchedDto) {
                $matched[] = $resolved;
            } else {
                $issues[] = $resolved;
            }
        }

        return new PhotoTextScheduleResultDto(
            matchedCount: count($matched),
            matched: $matched,
            issues: $issues,
            dateFrom: $dateFrom,
            dateTo: $dateTo,
            applied: false,
            categoriesApplied: [],
        );
    }

    /**
     * {@inheritDoc}
     *
     * Фото однозначно задаёт график на окно: в scope (указанные category_ids или все
     * категории ресторана) даты в диапазоне заменяются; блюда вне entries получают [].
     */
    public function apply(
        int $restaurantId,
        ?array $categoryIds,
        string $dateFrom,
        string $dateTo,
        array $entries,
    ): PhotoTextScheduleResultDto {
        $result = $this->match($restaurantId, $categoryIds, $dateFrom, $dateTo, $entries);

        if ($result->matchedCount === 0 || $result->matched === []) {
            return $result;
        }

        $datesByDishId = $this->aggregateDatesByDishId($result->matched);
        $categoryIdsToReplace = $this->resolveCategoryIdsToReplace(
            $restaurantId,
            $this->normalizeCategoryIds($categoryIds),
        );
        $categoriesApplied = [];

        foreach ($categoryIdsToReplace as $replaceCategoryId) {
            $changes = $this->buildFullCategoryChanges(
                $restaurantId,
                $replaceCategoryId,
                $datesByDishId,
            );

            if ($changes === []) {
                continue;
            }

            $this->scheduleService->syncSchedule(new DishAvailabilityUpdateDto(
                restaurantId: $restaurantId,
                categoryId: $replaceCategoryId,
                changes: $changes,
                dateFrom: $dateFrom,
                dateTo: $dateTo,
            ));

            $categoriesApplied[] = $replaceCategoryId;
        }

        sort($categoriesApplied);

        return $result->withApplied($categoriesApplied);
    }

    /**
     * Exact LOWER(name) в ресторане; при category_ids — только блюда этих категорий.
     *
     * @param  list<int>|null  $categoryIds
     */
    private function resolveEntry(
        PhotoTextScheduleEntryDto $entry,
        int $restaurantId,
        ?array $categoryIds,
    ): PhotoTextScheduleMatchedDto|PhotoTextScheduleIssueDto {
        $searchName = trim($entry->name);

        if ($searchName === '') {
            return new PhotoTextScheduleIssueDto(
                code: PhotoTextMatchIssueCode::DishNotFound,
                message: 'Пустое название блюда после нормализации.',
                rawTitle: $entry->name,
                dates: $entry->dates,
            );
        }

        $found = $this->findUniqueDish($searchName, $restaurantId, $categoryIds);

        if (! $found instanceof Dish) {
            return $this->issueFromFindFailure(
                $found,
                $searchName,
                $entry->name,
                $entry->dates,
                $restaurantId,
                $categoryIds,
            );
        }

        $category = $found->menuCategory;

        return new PhotoTextScheduleMatchedDto(
            rawTitle: $entry->name,
            dishId: (int) $found->id,
            dishName: (string) $found->name,
            categoryId: (int) $category->id,
            categoryName: (string) $category->name,
            dates: $entry->dates,
        );
    }

    /**
     * @param  list<int>|null  $categoryIds
     * @return Dish|PhotoTextMatchIssueCode
     */
    private function findUniqueDish(
        string $searchName,
        int $restaurantId,
        ?array $categoryIds,
    ): Dish|PhotoTextMatchIssueCode {
        $inRestaurant = $this->dishRepository->findByNameCaseInsensitive($searchName, $restaurantId);

        if ($categoryIds !== null) {
            $allowed = array_fill_keys($categoryIds, true);
            $inRestaurant = $inRestaurant->filter(
                static fn (Dish $dish): bool => isset($allowed[(int) $dish->menu_category_id]),
            )->values();
        }

        if ($inRestaurant->count() === 1) {
            /** @var Dish $dish */
            $dish = $inRestaurant->first();

            return $dish;
        }

        if ($inRestaurant->count() > 1) {
            return PhotoTextMatchIssueCode::DishAmbiguous;
        }

        return PhotoTextMatchIssueCode::DishNotFound;
    }

    /**
     * @param  list<string>  $dates
     * @param  list<int>|null  $categoryIds
     */
    private function issueFromFindFailure(
        PhotoTextMatchIssueCode $code,
        string $searchName,
        string $rawTitle,
        array $dates,
        int $restaurantId,
        ?array $categoryIds,
    ): PhotoTextScheduleIssueDto {
        $message = $code === PhotoTextMatchIssueCode::DishAmbiguous
            ? 'Найдено несколько блюд: '.$searchName
            : 'Блюдо не найдено: '.$searchName;

        if ($code === PhotoTextMatchIssueCode::DishNotFound) {
            $inRestaurant = $this->dishRepository->findByNameCaseInsensitive($searchName, $restaurantId);

            if ($categoryIds !== null && $inRestaurant->isNotEmpty()) {
                $message = 'Блюдо не относится к указанной категории: '.$searchName;
            } elseif ($inRestaurant->isEmpty()) {
                $anywhere = $this->dishRepository->findByNameCaseInsensitive($searchName);

                if ($anywhere->isNotEmpty()) {
                    $message = 'Блюдо не относится к указанному ресторану: '.$searchName;
                }
            }
        }

        return new PhotoTextScheduleIssueDto(
            code: $code,
            message: $message,
            rawTitle: $rawTitle,
            dates: $dates,
        );
    }

    /**
     * @param  list<PhotoTextScheduleMatchedDto>  $matched
     * @return array<int, list<string>> dish_id => dates
     */
    private function aggregateDatesByDishId(array $matched): array
    {
        $datesByDishId = [];

        foreach ($matched as $line) {
            $existing = $datesByDishId[$line->dishId] ?? [];
            $datesByDishId[$line->dishId] = array_values(array_unique([
                ...$existing,
                ...$line->dates,
            ]));
            sort($datesByDishId[$line->dishId]);
        }

        return $datesByDishId;
    }

    /**
     * Scope замены: указанные категории или все категории ресторана.
     *
     * @param  list<int>|null  $categoryIds
     * @return list<int>
     */
    private function resolveCategoryIdsToReplace(int $restaurantId, ?array $categoryIds): array
    {
        if ($categoryIds !== null) {
            return $categoryIds;
        }

        return array_values(array_map(
            static fn (MenuCategory $category): int => (int) $category->id,
            $this->menuCategoryRepository->listForAdmin($restaurantId),
        ));
    }

    /**
     * Все блюда категории: matched — даты с фото, остальные — пустой список (очистка).
     *
     * @param  array<int, list<string>>  $datesByDishId
     * @return list<DishAvailabilityChangeDto>
     */
    private function buildFullCategoryChanges(
        int $restaurantId,
        int $categoryId,
        array $datesByDishId,
    ): array {
        $dishes = $this->availabilityRepository->listDishesForCategory($restaurantId, $categoryId);

        return array_map(
            static fn (Dish $dish): DishAvailabilityChangeDto => new DishAvailabilityChangeDto(
                dishId: (int) $dish->id,
                dates: $datesByDishId[(int) $dish->id] ?? [],
            ),
            $dishes,
        );
    }

    /**
     * @param  list<int>|null  $categoryIds
     * @return list<int>|null
     */
    private function normalizeCategoryIds(?array $categoryIds): ?array
    {
        if ($categoryIds === null || $categoryIds === []) {
            return null;
        }

        $normalized = array_values(array_unique(array_map(
            static fn (mixed $id): int => (int) $id,
            $categoryIds,
        )));
        sort($normalized);

        return $normalized;
    }

    /**
     * @param  list<int>|null  $categoryIds
     *
     * @throws FoodDomainException
     */
    private function assertOptionalCategoriesBelongToRestaurant(?array $categoryIds, int $restaurantId): void
    {
        if ($categoryIds === null) {
            return;
        }

        foreach ($categoryIds as $categoryId) {
            $category = $this->menuCategoryRepository->findById($categoryId);

            if ($category === null || (int) $category->restaurant_id !== $restaurantId) {
                throw new FoodDomainException('Категория меню не найдена для выбранного ресторана.', 422);
            }
        }
    }
}
