<?php

declare(strict_types=1);

namespace App\Modules\SchedulePhoto\Application\Dto;

/**
 * Отчёт матчинга/записи графика (сериализуется в match_result).
 */
readonly class SchedulePhotoScheduleResultDto
{
    /**
     * @param  list<SchedulePhotoScheduleMatchedDto>  $matched
     * @param  list<SchedulePhotoScheduleIssueDto>  $issues
     * @param  list<int>  $categoriesApplied
     */
    public function __construct(
        public int $matchedCount,
        public array $matched,
        public array $issues,
        public string $dateFrom,
        public string $dateTo,
        public bool $applied = false,
        public array $categoriesApplied = [],
    ) {}

    /**
     * @return array{
     *     matched_count: int,
     *     matched: list<array<string, mixed>>,
     *     issues: list<array<string, mixed>>,
     *     date_from: string,
     *     date_to: string,
     *     applied: bool,
     *     categories_applied: list<int>
     * }
     */
    public function toArray(): array
    {
        return [
            'matched_count' => $this->matchedCount,
            'matched' => array_map(
                static fn (SchedulePhotoScheduleMatchedDto $line): array => $line->toArray(),
                $this->matched,
            ),
            'issues' => array_map(
                static fn (SchedulePhotoScheduleIssueDto $issue): array => $issue->toArray(),
                $this->issues,
            ),
            'date_from' => $this->dateFrom,
            'date_to' => $this->dateTo,
            'applied' => $this->applied,
            'categories_applied' => $this->categoriesApplied,
        ];
    }

    /**
     * Копия отчёта после успешной записи графика.
     *
     * @param  list<int>  $categoriesApplied
     */
    public function withApplied(array $categoriesApplied): self
    {
        return new self(
            matchedCount: $this->matchedCount,
            matched: $this->matched,
            issues: $this->issues,
            dateFrom: $this->dateFrom,
            dateTo: $this->dateTo,
            applied: true,
            categoriesApplied: $categoriesApplied,
        );
    }
}
