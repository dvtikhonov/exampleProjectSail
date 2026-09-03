<?php

declare(strict_types=1);

namespace App\Infrastructure\Laravel;

use App\Contracts\Shared\LocalFileWriterInterface;
use Illuminate\Filesystem\Filesystem;

/**
 * Laravel-адаптер {@see LocalFileWriterInterface} поверх Filesystem.
 */
final class LaravelLocalFileWriter implements LocalFileWriterInterface
{
    public function __construct(
        private readonly Filesystem $files,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function ensureDirectory(string $directory): void
    {
        if (! $this->files->isDirectory($directory)) {
            $this->files->makeDirectory($directory, 0755, true);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function put(string $path, string $contents): void
    {
        $this->files->put($path, $contents);
    }
}
