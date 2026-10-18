<?php

declare(strict_types=1);

namespace App\Modules\SchedulePhoto\Infrastructure\Persistence\Filesystem;

use App\Modules\SchedulePhoto\Application\Port\SchedulePhotoAliasDictionaryCacheInterface;
use App\Modules\SchedulePhoto\Application\Port\SchedulePhotoAliasDictionaryInterface;
use App\Modules\SchedulePhoto\Application\Port\SchedulePhotoAliasesWriterInterface;
use App\Modules\SchedulePhoto\Infrastructure\Dictionary\SchedulePhotoAliasMarkdownTable;
use RuntimeException;
use Throwable;

/**
 * Дописывает | OCR | Канон | в phototext-dish-aliases.md.
 *
 * Успех — запись во все существующие копии. Отсутствующий путь пропускается.
 * Один и тот же inode (два пути на один файл) пишется один раз.
 * Если любая копия не записалась, уже изменённые файлы откатываются и метод бросает ошибку.
 */
final class FilesystemSchedulePhotoAliasesWriter implements SchedulePhotoAliasesWriterInterface
{
    /**
     * @param  list<string>  $candidatePaths
     */
    public function __construct(
        private readonly array $candidatePaths,
        private readonly SchedulePhotoAliasDictionaryInterface $dictionary,
        private readonly SchedulePhotoAliasDictionaryCacheInterface $cache,
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
        $this->writeAllExistingCopies(function (string $path) use ($line): void {
            $this->appendLine($path, $line);
        });

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
        $this->writeAllExistingCopies(function (string $path) use ($ocr, $line): void {
            $this->replaceLineForOcr($path, $ocr, $line);
        });

        return true;
    }

    /**
     * Пишет во все существующие копии. Сбой любой копии откатывает уже изменённые.
     *
     * @param  callable(string): void  $mutate
     */
    private function writeAllExistingCopies(callable $mutate): void
    {
        $copies = $this->existingCopies();
        if ($copies === []) {
            throw new RuntimeException($this->writeFailureMessage('нет существующего файла'));
        }

        $snapshots = [];
        foreach ($copies as $path) {
            $before = file_get_contents($path);
            if (! is_string($before)) {
                throw new RuntimeException('Не удалось прочитать словарь алиасов: '.$path);
            }
            $snapshots[$path] = $before;
        }

        try {
            foreach ($copies as $path) {
                $mutate($path);
            }
        } catch (Throwable $e) {
            $restoreErrors = $this->restoreSnapshots($snapshots);
            if ($restoreErrors === []) {
                throw $e;
            }

            throw new RuntimeException(
                $e->getMessage().'; не удалось откатить: '.implode(', ', $restoreErrors),
                0,
                $e,
            );
        }

        $this->cache->clearCache();
    }

    /**
     * Существующие файлы без дублей одного inode.
     *
     * @return list<string>
     */
    private function existingCopies(): array
    {
        $copies = [];
        $seen = [];

        foreach ($this->candidatePaths as $path) {
            $trimmed = trim($path);
            if ($trimmed === '' || ! is_file($trimmed)) {
                continue;
            }

            $real = realpath($trimmed);
            $key = is_string($real) && $real !== '' ? $real : $trimmed;
            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $copies[] = $trimmed;
        }

        return $copies;
    }

    /**
     * @param  array<string, string>  $snapshots
     * @return list<string>
     */
    private function restoreSnapshots(array $snapshots): array
    {
        $failed = [];
        foreach ($snapshots as $path => $contents) {
            $current = file_get_contents($path);
            if ($current === $contents) {
                continue;
            }

            try {
                $this->putContents($path, $contents);
            } catch (RuntimeException) {
                $failed[] = $path;
            }
        }

        return $failed;
    }

    private function writeFailureMessage(string $detail): string
    {
        return 'Не удалось записать все существующие копии phototext-dish-aliases.md '
            .'('.$detail.'). '
            .'Проверьте mount .cursor/commands, права www-data и SCHEDULE_PHOTO_ALIASES_PATH. '
            .'Кандидаты: '.$this->describeCandidates();
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
        $this->putContents($path, $normalized);
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
            if (SchedulePhotoAliasMarkdownTable::isHeaderOrSeparator($ocrCell, $canonCell)) {
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

        $this->putContents($path, $normalized);
    }

    /**
     * Запись с блокировкой. Предупреждение PHP (например errno на /proc или bind mount)
     * становится исключением, даже если file_put_contents вернул число байт.
     */
    private function putContents(string $path, string $contents): void
    {
        $error = null;
        set_error_handler(static function (int $severity, string $message) use (&$error): bool {
            $error = $message;

            return true;
        });

        try {
            $written = file_put_contents($path, $contents, LOCK_EX);
        } finally {
            restore_error_handler();
        }

        if ($written === false || $error !== null) {
            throw new RuntimeException(
                'Не удалось записать словарь алиасов: '.$path
                .($error !== null ? ': '.$error : ''),
            );
        }
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
