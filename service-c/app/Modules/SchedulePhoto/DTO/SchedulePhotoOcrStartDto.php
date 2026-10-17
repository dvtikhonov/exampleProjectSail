<?php

declare(strict_types=1);

namespace App\Modules\SchedulePhoto\DTO;

/**
 * Handshake-запрос к sidecar OCR (POST /schedule-ocr).
 */
readonly class SchedulePhotoOcrStartDto
{
    /**
     * @param  list<int>  $categoryIds
     * @param  list<array{id: int, name: string, category_id: int, category_name: string}>  $catalogScope
     * @param  string  $aliasesMarkdown  таблица сокращений из phototext-dish-aliases.md
     * @param  string  $photoKey  относительный ключ от корня диска local
     * @param  string  $photoMimeType  image/jpeg|png|gif|webp
     */
    public function __construct(
        public string $runId,
        public string $photoKey,
        public string $dateFrom,
        public string $dateTo,
        public int $restaurantId,
        public array $categoryIds,
        public array $catalogScope,
        public string $aliasesMarkdown,
        public string $photoMimeType = 'image/jpeg',
    ) {}

    /**
     * Тело POST /schedule-ocr. Секрет уходит заголовком, байты фото sidecar читает сам.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'run_id' => $this->runId,
            'photo_key' => $this->photoKey,
            'photo_mime_type' => $this->photoMimeType,
            'date_from' => $this->dateFrom,
            'date_to' => $this->dateTo,
            'restaurant_id' => $this->restaurantId,
            'category_ids' => $this->categoryIds,
            'catalog_scope' => $this->catalogScope,
            'aliases_markdown' => $this->aliasesMarkdown,
        ];
    }
}
