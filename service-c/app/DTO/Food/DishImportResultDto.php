<?php

declare(strict_types=1);

namespace App\DTO\Food;

/**
 * Результат импорта блюд из таблицы.
 */
readonly class DishImportResultDto
{
    /**
     * @param  list<array{row: int, message: string}>  $errors
     */
    public function __construct(
        public int $importedCount,
        public array $errors,
    ) {}

    /**
     * @return array{imported_count: int, errors: list<array{row: int, message: string}>}
     */
    public function toArray(): array
    {
        return [
            'imported_count' => $this->importedCount,
            'errors' => $this->errors,
        ];
    }
}
