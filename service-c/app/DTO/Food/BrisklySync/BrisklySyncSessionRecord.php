<?php

declare(strict_types=1);

namespace App\DTO\Food\BrisklySync;

use App\Enums\Food\BrisklySync\BrisklySyncSessionStatus;

/**
 * Доменная запись сессии Briskly sync (без Bearer-токена).
 */
readonly class BrisklySyncSessionRecord
{
    /**
     * @param  list<BrisklySnapshotItemDto>|null  $brisklySnapshot
     * @param  list<array<string, mixed>>|null  $sourceLinesSnapshot
     * @param  array<string, mixed>|null  $proposals
     * @param  array<string, mixed>|null  $approvals
     * @param  array<string, mixed>|null  $applyReport
     * @param  list<int>|null  $allowedBrisklyCategoryIds
     */
    public function __construct(
        public string $id,
        public int $restaurantId,
        public ?int $createdByMaxUserId,
        public ?int $vpsCategoryId,
        public ?string $searchText,
        public ?string $clarification,
        public BrisklySyncSessionStatus $status,
        public ?array $brisklySnapshot,
        public ?array $sourceLinesSnapshot,
        public ?array $proposals,
        public ?array $approvals,
        public ?array $applyReport,
        public ?array $allowedBrisklyCategoryIds,
        public ?string $sourcePriceHash,
    ) {}

    /**
     * Мета сессии для GET (без token и без сырого snapshot целиком при желании).
     *
     * @return array{
     *     id: string,
     *     restaurant_id: int,
     *     vps_category_id: int|null,
     *     search_text: string|null,
     *     clarification: string|null,
     *     status: string,
     *     has_snapshot: bool,
     *     has_proposals: bool,
     *     source_price_hash: string|null
     * }
     */
    public function toMetaArray(): array
    {
        return [
            'id' => $this->id,
            'restaurant_id' => $this->restaurantId,
            'vps_category_id' => $this->vpsCategoryId,
            'search_text' => $this->searchText,
            'clarification' => $this->clarification,
            'status' => $this->status->value,
            'has_snapshot' => $this->brisklySnapshot !== null,
            'has_proposals' => $this->proposals !== null,
            'source_price_hash' => $this->sourcePriceHash,
        ];
    }
}
