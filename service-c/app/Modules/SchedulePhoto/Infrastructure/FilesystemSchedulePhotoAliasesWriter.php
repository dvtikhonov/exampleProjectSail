<?php

declare(strict_types=1);

namespace App\Modules\SchedulePhoto\Infrastructure;

use App\Modules\SchedulePhoto\Contracts\SchedulePhotoAliasDictionaryInterface;
use App\Modules\SchedulePhoto\Contracts\SchedulePhotoAliasesWriterInterface;
use RuntimeException;
use Throwable;

/**
 * Дописывает | OCR | Канон | в phototext-dish-aliases.md.
 *
 * Пишет во все доступные копии; успех — если записалась хотя бы одна.
 * Docker single-file bind mount часто врёт в is_writable() и падает на put —
 * такие пути пропускаем, не откатывая уже успешную запись в .cursor/commands.
 */
final class FilesystemSchedulePhotoAliasesWriter implements SchedulePhotoAliasesWriterInterface
{
    /**
     * @param  list<string>  $candidatePaths
     */
    public function __construct(
        private readonly array $candidatePaths,
        private readonly SchedulePhotoAliasDictionaryInterface $dictionary,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function appendPairIfMissing(string $ocrTitle, string $canonName): bool
    {
        $ocr = trim($ocrTitle);
        $canon = trim($canonName);
        if ($ocr === '' || $canon === '') {
            return false;
        }

        if ($this->dictionary->resolveCanon($ocr) !== null) {
            return false;
        }

        $line = '| '.$this->cell($ocr).' | '.$this->cell($canon).' |';
        $writtenPaths = [];
        $errors = [];

        foreach ($this->candidatePaths as $path) {
            $trimmed = trim($path);
            if ($trimmed === '' || ! is_file($trimmed)) {
                continue;
            }

            try {
                $this->appendLine($trimmed, $line);
                $writtenPaths[] = $trimmed;
            } catch (Throwable $e) {
                $errors[] = $trimmed.': '.$e->getMessage();
            }
        }

        if ($writtenPaths === []) {
            throw new RuntimeException(
                'Нет доступного для записи phototext-dish-aliases.md '
                .'(проверьте mount .cursor/commands, права www-data и SCHEDULE_PHOTO_ALIASES_PATH). '
                .'Кандидаты: '.$this->describeCandidates()
                .($errors === [] ? '' : '; ошибки: '.implode('; ', $errors)),
            );
        }

        $this->dictionary->clearCache();

        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function upsertPair(string $ocrTitle, string $canonName): bool
    {
        $ocr = trim($ocrTitle);
        $canon = trim($canonName);
        if ($ocr === '' || $canon === '') {
            return false;
        }

        $existing = $this->dictionary->resolveCanon($ocr);
        if ($existing !== null
            && $this->dictionary->normalizeKey($existing) === $this->dictionary->normalizeKey($canon)
        ) {
            return false;
        }

        if ($existing === null) {
            return $this->appendPairIfMissing($ocr, $canon);
        }

        $line = '| '.$this->cell($ocr).' | '.$this->cell($canon).' |';
        $writtenPaths = [];
        $errors = [];

        foreach ($this->candidatePaths as $path) {
            $trimmed = trim($path);
            if ($trimmed === '' || ! is_file($trimmed)) {
                continue;
            }

            try {
                $this->replaceLineForOcr($trimmed, $ocr, $line);
                $writtenPaths[] = $trimmed;
            } catch (Throwable $e) {
                $errors[] = $trimmed.': '.$e->getMessage();
            }
        }

        if ($writtenPaths === []) {
            throw new RuntimeException(
                'Нет доступного для записи phototext-dish-aliases.md '
                .'(проверьте mount .cursor/commands, права www-data и SCHEDULE_PHOTO_ALIASES_PATH). '
                .'Кандидаты: '.$this->describeCandidates()
                .($errors === [] ? '' : '; ошибки: '.implode('; ', $errors)),
            );
        }

        $this->dictionary->clearCache();

        return true;
    }

    /**
     * Краткий статус кандидатов для сообщения об ошибке.
     */
    private function describeCandidates(): string
    {
        if ($this->candidatePaths === []) {
            return '(список путей пуст)';
        }

        $parts = [];
        foreach ($this->candidatePaths as $path) {
            $trimmed = trim($path);
            if ($trimmed === '') {
                continue;
            }

            if (! is_file($trimmed)) {
                $parts[] = $trimmed.'[missing]';

                continue;
            }

            $parts[] = $trimmed.(is_writable($trimmed) ? '[writable?]' : '[not-writable]');
        }

        return $parts === [] ? '(нет непустых путей)' : implode('; ', $parts);
    }

    private function appendLine(string $path, string $line): void
    {
        $contents = file_get_contents($path);
        if (! is_string($contents)) {
            throw new RuntimeException('Не удалось прочитать словарь алиасов: '.$path);
        }

        $normalized = rtrim($contents, "\r\n")."\n".$line."\n";
        $ok = file_put_contents($path, $normalized, LOCK_EX);
        if ($ok === false) {
            throw new RuntimeException('Не удалось записать словарь алиасов: '.$path);
        }
    }

    /**
     * Заменяет строку таблицы с тем же нормализованным OCR; если нет — дописывает.
     */
    private function replaceLineForOcr(string $path, string $ocr, string $newLine): void
    {
        $contents = file_get_contents($path);
        if (! is_string($contents)) {
            throw new RuntimeException('Не удалось прочитать словарь алиасов: '.$path);
        }

        $needle = $this->dictionary->normalizeKey($ocr);
        $lines = preg_split('/\R/u', $contents) ?: [];
        $found = false;

        foreach ($lines as $index => $line) {
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

            $ocrCell = $cells[0];
            $canonCell = $cells[1];
            if ($this->isHeaderOrSeparator($ocrCell, $canonCell)) {
                continue;
            }

            if ($this->dictionary->normalizeKey($ocrCell) !== $needle) {
                continue;
            }

            $lines[$index] = $newLine;
            $found = true;
            break;
        }

        $normalized = $found
            ? implode("\n", $lines)."\n"
            : rtrim($contents, "\r\n")."\n".$newLine."\n";

        $ok = file_put_contents($path, $normalized, LOCK_EX);
        if ($ok === false) {
            throw new RuntimeException('Не удалось записать словарь алиасов: '.$path);
        }
    }

    private function isHeaderOrSeparator(string $ocr, string $canon): bool
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

    /**
     * Ячейка markdown-таблицы: разделитель и переводы строк не ломают строку,
     * длина ограничена, чтобы словарь не раздувал промпт.
     */
    private function cell(string $value): string
    {
        $sanitized = str_replace(
            ['|', "\n", "\r"],
            ['/', ' ', ''],
            $value,
        );

        return mb_substr($sanitized, 0, 200, 'UTF-8');
    }
}
