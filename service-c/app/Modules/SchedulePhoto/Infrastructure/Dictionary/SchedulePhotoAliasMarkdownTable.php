<?php

declare(strict_types=1);

namespace App\Modules\SchedulePhoto\Infrastructure\Dictionary;

/**
 * Правила разбора markdown-таблицы | OCR | Канон | (заголовок и сепаратор).
 *
 * Один источник истины для Dictionary и Writer.
 */
final class SchedulePhotoAliasMarkdownTable
{
    public static function isHeaderOrSeparator(string $ocr, string $canon): bool
    {
        $ocrLower = mb_strtolower($ocr, 'UTF-8');
        $canonLower = mb_strtolower($canon, 'UTF-8');

        if (str_contains($ocr, '---') || str_contains($canon, '---')) {
            return true;
        }

        if ($ocrLower === 'ocr / бланк' || $ocrLower === 'ocr' || $ocrLower === 'бланк') {
            return true;
        }

        if ($canonLower === 'канон каталога' || $canonLower === 'канон') {
            return true;
        }

        return false;
    }
}
