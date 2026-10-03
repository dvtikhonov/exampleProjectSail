<?php

declare(strict_types=1);

namespace App\DTO\Food\BrisklySync;

/**
 * Секция sync-results с cap и truncated.
 *
 * @template T of object
 */
readonly class SyncResultsSectionDto
{
    /**
     * @param  list<T>  $items
     */
    public function __construct(
        public array $items,
        public int $total,
        public int $shown,
        public bool $truncated,
    ) {}

    /**
     * @template TItem of object
     *
     * @param  list<TItem>  $all
     * @return self<TItem>
     */
    public static function fromAll(array $all, int $cap): self
    {
        $total = count($all);
        $items = array_slice($all, 0, max(0, $cap));

        return new self(
            items: $items,
            total: $total,
            shown: count($items),
            truncated: $total > count($items),
        );
    }

    /**
     * @param  callable(object): array<string, mixed>  $itemToArray
     * @return array{items: list<array<string, mixed>>, total: int, shown: int, truncated: bool}
     */
    public function toArray(callable $itemToArray): array
    {
        return [
            'items' => array_map($itemToArray, $this->items),
            'total' => $this->total,
            'shown' => $this->shown,
            'truncated' => $this->truncated,
        ];
    }
}
