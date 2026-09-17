<?php

declare(strict_types=1);

namespace App\Services\Food\PhotoText;

use App\Contracts\Food\Menu\DishAvailabilityScheduleRepositoryInterface;
use App\Contracts\Food\Menu\DishAvailabilityScheduleWriterInterface;
use App\Contracts\Shared\TransactionManagerInterface;
use App\DTO\Food\Menu\DishAvailabilityChangeDto;
use App\DTO\Food\Menu\DishAvailabilityUpdateDto;
use App\DTO\Food\Menu\DishRecord;
use App\DTO\Food\Menu\MenuCategoryRecord;
use App\DTO\Food\PhotoText\PhotoTextScheduleEntryDto;
use App\DTO\Food\PhotoText\PhotoTextScheduleMatchedDto;
use App\DTO\Food\PhotoText\PhotoTextScheduleResultDto;

/**
 * Match + полная замена графика PhotoText в окне (фото — источник истины).
 */
class PhotoTextScheduleApplier
{
    public function __construct(
        private readonly PhotoTextScheduleMatcher $matcher,
        private readonly PhotoTextScheduleCategoryScope $categoryScope,
        private readonly DishAvailabilityScheduleRepositoryInterface $availabilityRepository,
        private readonly DishAvailabilityScheduleWriterInterface $scheduleWriter,
        private readonly TransactionManagerInterface $transactionManager,
    ) {}

    /**
     * Match + полная замена графика в окне.
     * Scope: указанные category_ids или все категории ресторана; блюда вне entries очищаются.
     * Замена по всем категориям scope — в одной транзакции (вложенные TX writer — savepoints).
     *
     * @param  list<int>|null  $categoryIds
     * @param  list<PhotoTextScheduleEntryDto>  $entries
     */
    public function apply(
        int $restaurantId,
        ?array $categoryIds,
        string $dateFrom,
        string $dateTo,
        array $entries,
    ): PhotoTextScheduleResultDto {
        $result = $this->matcher->match($restaurantId, $categoryIds, $dateFrom, $dateTo, $entries);

        if ($result->matchedCount === 0 || $result->matched === []) {
            return $result;
        }

        $datesByDishId = $this->aggregateDatesByDishId($result->matched);
        $categoryIdsToReplace = $this->resolveCategoryIdsToReplace(
            $restaurantId,
            $this->categoryScope->normalizeCategoryIds($categoryIds),
        );

        /** @var list<int> $categoriesApplied */
        $categoriesApplied = $this->transactionManager->run(
            function () use (
                $restaurantId,
                $categoryIdsToReplace,
                $datesByDishId,
                $dateFrom,
                $dateTo,
            ): array {
                $applied = [];

                foreach ($categoryIdsToReplace as $replaceCategoryId) {
                    $changes = $this->buildFullCategoryChanges(
                        $restaurantId,
                        $replaceCategoryId,
                        $datesByDishId,
                    );

                    if ($changes === []) {
                        continue;
                    }

                    $this->scheduleWriter->syncSchedule(new DishAvailabilityUpdateDto(
                        restaurantId: $restaurantId,
                        categoryId: $replaceCategoryId,
                        changes: $changes,
                        dateFrom: $dateFrom,
                        dateTo: $dateTo,
                    ));

                    $applied[] = $replaceCategoryId;
                }

                return $applied;
            },
        );

        sort($categoriesApplied);

        return $result->withApplied($categoriesApplied);
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
            static fn (MenuCategoryRecord $category): int => $category->id,
            $this->categoryScope->listCategoryRecords($restaurantId),
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
            static fn (DishRecord $dish): DishAvailabilityChangeDto => new DishAvailabilityChangeDto(
                dishId: $dish->id,
                dates: $datesByDishId[$dish->id] ?? [],
            ),
            $dishes,
        );
    }
}
