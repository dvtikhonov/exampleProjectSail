<?php

declare(strict_types=1);

namespace App\Modules\SchedulePhoto\Infrastructure\Dictionary;

use App\Modules\SchedulePhoto\Application\Port\SchedulePhotoAliasDictionaryCacheInterface;
use App\Modules\SchedulePhoto\Application\Port\SchedulePhotoAliasDictionaryInterface;
use App\Modules\SchedulePhoto\Application\Port\SchedulePhotoAliasesReaderInterface;

/**
 * Парсит markdown-таблицу | OCR | Канон | в map нормализованный ключ → канон.
 */
final class MarkdownSchedulePhotoAliasDictionary implements SchedulePhotoAliasDictionaryCacheInterface, SchedulePhotoAliasDictionaryInterface
{
    /** @var array<string, string>|null */
    private ?array $map = null;

    public function __construct(
        private readonly SchedulePhotoAliasesReaderInterface $reader,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function resolveCanon(string $rawTitle): ?string
    {
        $key = $this->normalizeKey($rawTitle);
        if ($key === '') {
            return null;
        }

        $map = $this->map();

        return $map[$key] ?? null;
    }

    /**
     * {@inheritDoc}
     */
    public function normalizeKey(string $value): string
    {
        $trimmed = trim($value);
        if ($trimmed === '') {
            return '';
        }

        $withoutQuotes = str_replace(['«', '»', '"', "'", '“', '”'], '', $trimmed);
        $yo = str_replace(['Ё', 'ё'], ['Е', 'е'], $withoutQuotes);
        $collapsed = preg_replace('/\s+/u', ' ', $yo) ?? $yo;

        return mb_strtolower(trim($collapsed), 'UTF-8');
    }

    /**
     * {@inheritDoc}
     */
    public function clearCache(): void
    {
        $this->map = null;
    }

    /**
     * @return array<string, string>
     */
    private function map(): array
    {
        if ($this->map !== null) {
            return $this->map;
        }

        $this->map = $this->parseMarkdown($this->reader->readMarkdown());

        return $this->map;
    }

    /**
     * @return array<string, string>
     */
    private function parseMarkdown(string $markdown): array
    {
        $result = [];
        if (trim($markdown) === '') {
            return $result;
        }

        foreach (preg_split('/\R/u', $markdown) ?: [] as $line) {
            $trimmed = trim($line);
            if ($trimmed === '' || ! str_starts_with($trimmed, '|')) {
                continue;
            }

            $cells = array_values(array_filter(
                array_map('trim', explode('|', trim($trimmed, '|'))),
                static fn (string $cell): bool => $cell !== '',
            ));

            if (count($cells) < 2) {
                continue;
            }

            $ocr = $cells[0];
            $canon = $cells[1];

            if (SchedulePhotoAliasMarkdownTable::isHeaderOrSeparator($ocr, $canon)) {
                continue;
            }

            $key = $this->normalizeKey($ocr);
            $canonTrimmed = trim($canon);
            if ($key === '' || $canonTrimmed === '') {
                continue;
            }

            $result[$key] = $canonTrimmed;
        }

        return $result;
    }
}
