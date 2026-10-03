<?php

declare(strict_types=1);

namespace App\DTO\Food\BrisklySync;

/**
 * Категория Briskly для select CREATE.
 */
readonly class BrisklyCategoryDto
{
    public function __construct(
        public int $id,
        public string $name,
        public ?int $catalogId = null,
        public ?int $parentId = null,
    ) {}

    /**
     * @param  array{id?: mixed, name?: mixed, catalog_id?: mixed, parent_id?: mixed}  $data
     */
    public static function fromArray(array $data): self
    {
        $catalogId = $data['catalog_id'] ?? null;
        $parentId = $data['parent_id'] ?? null;

        return new self(
            id: (int) ($data['id'] ?? 0),
            name: (string) ($data['name'] ?? ''),
            catalogId: $catalogId === null || $catalogId === '' ? null : (int) $catalogId,
            parentId: $parentId === null || $parentId === '' ? null : (int) $parentId,
        );
    }

    /**
     * @return array{id: int, name: string, catalog_id: int|null, parent_id: int|null}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'catalog_id' => $this->catalogId,
            'parent_id' => $this->parentId,
        ];
    }
}
