<?php

declare(strict_types=1);

namespace App\Services\Food\PhotoText;

use App\Contracts\Food\PhotoText\PhotoTextDishNameMatcherInterface;
use App\Contracts\Food\PhotoText\PhotoTextScheduleMatcherInterface;
use App\DTO\Food\PhotoText\PhotoTextScheduleEntryDto;
use App\DTO\Food\PhotoText\PhotoTextScheduleIssueDto;
use App\DTO\Food\PhotoText\PhotoTextScheduleMatchedDto;
use App\DTO\Food\PhotoText\PhotoTextScheduleResultDto;

/**
 * Exact match имён графика PhotoText (без записи в БД).
 */
class PhotoTextScheduleMatcher implements PhotoTextScheduleMatcherInterface
{
    public function __construct(
        private readonly PhotoTextDishNameMatcherInterface $dishNameMatcher,
        private readonly PhotoTextScheduleCategoryScope $categoryScope,
        private readonly PhotoTextMatchIssueFactory $matchIssueFactory,
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
        $scopeCategoryIds = $this->categoryScope->normalizeCategoryIds($categoryIds);
        $this->categoryScope->assertOptionalCategoriesBelongToRestaurant($scopeCategoryIds, $restaurantId);

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
            return $this->matchIssueFactory->emptyNameScheduleIssue($entry->name, $entry->dates);
        }

        $matchResult = $this->dishNameMatcher->match($searchName, $restaurantId, $categoryIds);

        if (! $matchResult->isSuccess()) {
            return $this->matchIssueFactory->scheduleIssueFromMatchFailure(
                $matchResult,
                $entry->name,
                $searchName,
                $entry->dates,
            );
        }

        $found = $matchResult->dish;
        $category = $found->menuCategory;

        return new PhotoTextScheduleMatchedDto(
            rawTitle: $entry->name,
            dishId: $found->id,
            dishName: $found->name,
            categoryId: (int) ($category?->id ?? 0),
            categoryName: (string) ($category?->name ?? ''),
            dates: $entry->dates,
        );
    }
}
