<?php

declare(strict_types=1);

namespace App\Modules\SchedulePhoto\Contracts;

/**
 * Чтение словаря OCR→канон из phototext-dish-aliases.md (без CRUD/БД).
 */
interface SchedulePhotoAliasesReaderInterface
{
    /**
     * Markdown-таблица сокращений для промпта LLM (или пустая строка, если файл не найден).
     */
    public function readMarkdown(): string;
}
