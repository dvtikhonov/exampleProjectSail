<?php

declare(strict_types=1);

namespace App\DTO\Food\PhotoText;

/**
 * Отчёт оформления PhotoText: matched_count, matched[], issues[], order_id?.
 */
readonly class PhotoTextPlacementResultDto
{
    /**
     * @param  list<PhotoTextMatchedLineDto>  $matched
     * @param  list<PhotoTextIssueDto>  $issues
     */
    public function __construct(
        public int $matchedCount,
        public array $matched,
        public array $issues,
        public ?int $orderId = null,
    ) {}

    /**
     * @return array{
     *     matched_count: int,
     *     matched: list<array<string, mixed>>,
     *     issues: list<array<string, mixed>>,
     *     order_id: int|null
     * }
     */
    public function toArray(): array
    {
        return [
            'matched_count' => $this->matchedCount,
            'matched' => array_map(
                static fn (PhotoTextMatchedLineDto $line): array => $line->toArray(),
                $this->matched,
            ),
            'issues' => array_map(
                static fn (PhotoTextIssueDto $issue): array => $issue->toArray(),
                $this->issues,
            ),
            'order_id' => $this->orderId,
        ];
    }

    /**
     * Копия отчёта с идентификатором созданного заказа.
     */
    public function withOrderId(?int $orderId): self
    {
        return new self(
            matchedCount: $this->matchedCount,
            matched: $this->matched,
            issues: $this->issues,
            orderId: $orderId,
        );
    }
}
