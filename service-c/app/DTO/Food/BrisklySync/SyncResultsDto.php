<?php

declare(strict_types=1);

namespace App\DTO\Food\BrisklySync;

/**
 * Полный ответ sync-results (секции A/B + counts).
 */
readonly class SyncResultsDto
{
    /**
     * @param  SyncResultsSectionDto<PriceDiffItemDto>  $priceUpdates
     * @param  SyncResultsSectionDto<VpsOnlyCreateItemDto>  $creates
     */
    public function __construct(
        public SyncResultsSectionDto $priceUpdates,
        public SyncResultsSectionDto $creates,
        public SyncResultsCountsDto $counts,
    ) {}

    /**
     * @param  array{
     *     price_updates?: array{items?: list<array<string, mixed>>, total?: mixed, shown?: mixed, truncated?: mixed},
     *     creates?: array{items?: list<array<string, mixed>>, total?: mixed, shown?: mixed, truncated?: mixed},
     *     counts?: array{skipped_briskly_only?: mixed, ambiguous?: mixed, equal_price?: mixed}
     * }  $data
     */
    public static function fromArray(array $data): self
    {
        $priceRaw = is_array($data['price_updates'] ?? null) ? $data['price_updates'] : [];
        $createRaw = is_array($data['creates'] ?? null) ? $data['creates'] : [];
        $countsRaw = is_array($data['counts'] ?? null) ? $data['counts'] : [];

        $priceItems = [];
        foreach ($priceRaw['items'] ?? [] as $item) {
            if (is_array($item)) {
                $priceItems[] = PriceDiffItemDto::fromArray($item);
            }
        }

        $createItems = [];
        foreach ($createRaw['items'] ?? [] as $item) {
            if (is_array($item)) {
                $createItems[] = VpsOnlyCreateItemDto::fromArray($item);
            }
        }

        return new self(
            priceUpdates: new SyncResultsSectionDto(
                items: $priceItems,
                total: (int) ($priceRaw['total'] ?? count($priceItems)),
                shown: (int) ($priceRaw['shown'] ?? count($priceItems)),
                truncated: (bool) ($priceRaw['truncated'] ?? false),
            ),
            creates: new SyncResultsSectionDto(
                items: $createItems,
                total: (int) ($createRaw['total'] ?? count($createItems)),
                shown: (int) ($createRaw['shown'] ?? count($createItems)),
                truncated: (bool) ($createRaw['truncated'] ?? false),
            ),
            counts: SyncResultsCountsDto::fromArray($countsRaw),
        );
    }

    /**
     * @return array{
     *     price_updates: array{items: list<array<string, mixed>>, total: int, shown: int, truncated: bool},
     *     creates: array{items: list<array<string, mixed>>, total: int, shown: int, truncated: bool},
     *     counts: array{skipped_briskly_only: int, ambiguous: int, equal_price: int}
     * }
     */
    public function toArray(): array
    {
        return [
            'price_updates' => $this->priceUpdates->toArray(
                static fn (PriceDiffItemDto $item): array => $item->toArray(),
            ),
            'creates' => $this->creates->toArray(
                static fn (VpsOnlyCreateItemDto $item): array => $item->toArray(),
            ),
            'counts' => $this->counts->toArray(),
        ];
    }
}
