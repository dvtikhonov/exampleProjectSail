<?php

declare(strict_types=1);

namespace App\DTO\Food\BrisklySync;

/**
 * Строка секции UPDATE (расхождение цен).
 */
readonly class PriceDiffItemDto
{
    public function __construct(
        public string $lineKey,
        public string $displayName,
        public int $brisklyItemId,
        public string $brisklyDisplayName,
        public string $sourcePrice,
        public string $brisklyPrice,
        public ?string $compareName = null,
    ) {}

    /**
     * @param  array{
     *     line_key?: mixed,
     *     display_name?: mixed,
     *     briskly_item_id?: mixed,
     *     briskly_display_name?: mixed,
     *     source_price?: mixed,
     *     briskly_price?: mixed,
     *     compare_name?: mixed
     * }  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            lineKey: (string) ($data['line_key'] ?? ''),
            displayName: (string) ($data['display_name'] ?? ''),
            brisklyItemId: (int) ($data['briskly_item_id'] ?? 0),
            brisklyDisplayName: (string) ($data['briskly_display_name'] ?? ''),
            sourcePrice: (string) ($data['source_price'] ?? '0.00'),
            brisklyPrice: (string) ($data['briskly_price'] ?? '0.00'),
            compareName: isset($data['compare_name']) ? (string) $data['compare_name'] : null,
        );
    }

    /**
     * @return array{
     *     line_key: string,
     *     display_name: string,
     *     briskly_item_id: int,
     *     briskly_display_name: string,
     *     source_price: string,
     *     briskly_price: string
     * }
     */
    public function toArray(): array
    {
        return [
            'line_key' => $this->lineKey,
            'display_name' => $this->displayName,
            'briskly_item_id' => $this->brisklyItemId,
            'briskly_display_name' => $this->brisklyDisplayName,
            'source_price' => $this->sourcePrice,
            'briskly_price' => $this->brisklyPrice,
        ];
    }
}
