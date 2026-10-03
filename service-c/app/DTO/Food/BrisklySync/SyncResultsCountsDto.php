<?php

declare(strict_types=1);

namespace App\DTO\Food\BrisklySync;

/**
 * Счётчики классификации вне таблиц UI.
 */
readonly class SyncResultsCountsDto
{
    public function __construct(
        public int $skippedBrisklyOnly,
        public int $ambiguous,
        public int $equalPrice,
    ) {}

    /**
     * @param  array{skipped_briskly_only?: mixed, ambiguous?: mixed, equal_price?: mixed}  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            skippedBrisklyOnly: (int) ($data['skipped_briskly_only'] ?? 0),
            ambiguous: (int) ($data['ambiguous'] ?? 0),
            equalPrice: (int) ($data['equal_price'] ?? 0),
        );
    }

    /**
     * @return array{skipped_briskly_only: int, ambiguous: int, equal_price: int}
     */
    public function toArray(): array
    {
        return [
            'skipped_briskly_only' => $this->skippedBrisklyOnly,
            'ambiguous' => $this->ambiguous,
            'equal_price' => $this->equalPrice,
        ];
    }
}
