<?php

declare(strict_types=1);

namespace App\DTO\Food\BrisklySync;

use App\Enums\Food\Menu\DailyMenuLineType;

/**
 * Позиция source-каталога VPS для синхронизации с Briskly.
 */
readonly class SourceMenuLineDto
{
    /**
     * @param  list<int>  $partDishIds
     * @param  string  $brisklyCreateName  Имя для CREATE в Briskly («наименование, вес»); для match/UI не используется.
     */
    public function __construct(
        public string $lineKey,
        public DailyMenuLineType $type,
        public string $displayName,
        public string $price,
        public array $partDishIds,
        public string $brisklyCreateName = '',
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $partDishIds = [];
        $rawIds = $data['part_dish_ids'] ?? [];
        if (is_array($rawIds)) {
            foreach ($rawIds as $id) {
                $partDishIds[] = (int) $id;
            }
        }

        $type = DailyMenuLineType::tryFrom((string) ($data['type'] ?? ''))
            ?? DailyMenuLineType::Single;

        return new self(
            lineKey: (string) ($data['line_key'] ?? ''),
            type: $type,
            displayName: (string) ($data['display_name'] ?? ''),
            price: (string) ($data['price'] ?? '0'),
            partDishIds: $partDishIds,
            brisklyCreateName: (string) ($data['briskly_create_name'] ?? ''),
        );
    }

    /**
     * Представление позиции для JSON API.
     *
     * @return array{
     *     line_key: string,
     *     type: string,
     *     display_name: string,
     *     price: string,
     *     part_dish_ids: list<int>,
     *     briskly_create_name: string
     * }
     */
    public function toArray(): array
    {
        return [
            'line_key' => $this->lineKey,
            'type' => $this->type->value,
            'display_name' => $this->displayName,
            'price' => $this->price,
            'part_dish_ids' => $this->partDishIds,
            'briskly_create_name' => $this->brisklyCreateName !== ''
                ? $this->brisklyCreateName
                : $this->displayName,
        ];
    }
}
