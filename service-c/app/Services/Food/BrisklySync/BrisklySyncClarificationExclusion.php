<?php

declare(strict_types=1);

namespace App\Services\Food\BrisklySync;

use App\DTO\Food\BrisklySync\BrisklySnapshotItemDto;
use App\DTO\Food\BrisklySync\SourceMenuLineDto;

/**
 * Детерминированное исключение позиций по clarification вида «без X» / «только без X».
 *
 * Нормализация имён («игнорировать вес») сюда не входит — её обрабатывает LLM.
 */
final class BrisklySyncClarificationExclusion
{
    /**
     * Извлекает стемы исключаемых слов из clarification.
     *
     * @return list<string>
     */
    public static function needles(?string $clarification): array
    {
        $text = trim((string) $clarification);
        if ($text === '') {
            return [];
        }

        if (preg_match_all(
            '/(?:^|[\s,;:]+|(?:только\s+))без\s+([^\s,.;:!?]+)/iu',
            $text,
            $matches,
        ) === false || $matches[1] === []) {
            return [];
        }

        $needles = [];
        foreach ($matches[1] as $raw) {
            $stem = self::stem((string) $raw);
            if ($stem === '' || mb_strlen($stem) < 3) {
                continue;
            }
            $needles[$stem] = $stem;
        }

        return array_values($needles);
    }

    /**
     * Имя проходит фильтр, если не содержит ни одного стема исключения.
     *
     * @param  list<string>  $needles
     */
    public static function allows(string $name, array $needles): bool
    {
        if ($needles === []) {
            return true;
        }

        $haystack = mb_strtolower($name);
        foreach ($needles as $needle) {
            if ($needle !== '' && mb_strpos($haystack, $needle) !== false) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  list<SourceMenuLineDto>  $lines
     * @param  list<string>  $needles
     * @return list<SourceMenuLineDto>
     */
    public static function filterSourceLines(array $lines, array $needles): array
    {
        if ($needles === []) {
            return $lines;
        }

        $filtered = [];
        foreach ($lines as $line) {
            if (! $line instanceof SourceMenuLineDto) {
                continue;
            }
            if (self::allows($line->displayName, $needles)) {
                $filtered[] = $line;
            }
        }

        return $filtered;
    }

    /**
     * @param  list<BrisklySnapshotItemDto>  $items
     * @param  list<string>  $needles
     * @return list<BrisklySnapshotItemDto>
     */
    public static function filterSnapshot(array $items, array $needles): array
    {
        if ($needles === []) {
            return $items;
        }

        $filtered = [];
        foreach ($items as $item) {
            if (! $item instanceof BrisklySnapshotItemDto) {
                continue;
            }
            if (self::allows($item->name, $needles)) {
                $filtered[] = $item;
            }
        }

        return $filtered;
    }

    private static function stem(string $word): string
    {
        $w = mb_strtolower(trim($word, " \t\n\r\0\x0B\"'«»()[]"));
        if ($w === '') {
            return '';
        }

        foreach (['ями', 'ами', 'ой', 'ей', 'ом', 'ем', 'ах', 'ях', 'ы', 'и', 'у', 'ю', 'а', 'я', 'е', 'о'] as $suffix) {
            $suffixLen = mb_strlen($suffix);
            if (mb_strlen($w) > $suffixLen + 2 && str_ends_with($w, $suffix)) {
                return mb_substr($w, 0, -$suffixLen);
            }
        }

        return $w;
    }
}
