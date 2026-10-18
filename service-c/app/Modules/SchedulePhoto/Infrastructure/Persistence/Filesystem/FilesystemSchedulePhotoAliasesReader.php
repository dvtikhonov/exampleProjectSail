<?php

declare(strict_types=1);

namespace App\Modules\SchedulePhoto\Infrastructure\Persistence\Filesystem;

use App\Modules\SchedulePhoto\Application\Port\SchedulePhotoAliasesReaderInterface;

/**
 * Читает phototext-dish-aliases.md из списка путей (первый существующий непустой).
 * Без БД: словарь только в markdown (дозапись — SchedulePhotoAliasesWriter).
 */
final class FilesystemSchedulePhotoAliasesReader implements SchedulePhotoAliasesReaderInterface
{
    /**
     * @param  list<string>  $candidatePaths
     */
    public function __construct(
        private readonly array $candidatePaths,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function readMarkdown(): string
    {
        foreach ($this->candidatePaths as $path) {
            $trimmed = trim($path);
            if ($trimmed === '' || ! is_file($trimmed) || ! is_readable($trimmed)) {
                continue;
            }

            $contents = file_get_contents($trimmed);
            if (! is_string($contents) || trim($contents) === '') {
                continue;
            }

            return $contents;
        }

        return '';
    }
}
