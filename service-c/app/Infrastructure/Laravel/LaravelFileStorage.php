<?php

declare(strict_types=1);

namespace App\Infrastructure\Laravel;

use App\Contracts\Shared\FileStorageInterface;
use Illuminate\Contracts\Filesystem\Factory as FilesystemFactory;

/**
 * Laravel-адаптер {@see FileStorageInterface} поверх Storage disks.
 */
class LaravelFileStorage implements FileStorageInterface
{
    public function __construct(
        private readonly FilesystemFactory $filesystem,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function makeDirectory(string $path, string $disk = 'public'): bool
    {
        return $this->filesystem->disk($disk)->makeDirectory($path);
    }

    /**
     * {@inheritDoc}
     */
    public function put(string $path, string $contents, string $disk = 'public'): bool
    {
        return $this->filesystem->disk($disk)->put($path, $contents);
    }

    /**
     * {@inheritDoc}
     */
    public function putFileAs(
        string $directory,
        string $localPath,
        string $filename,
        string $disk = 'public',
    ): string|false {
        $stored = $this->filesystem->disk($disk)->putFileAs($directory, $localPath, $filename);

        return $stored !== false ? (string) $stored : false;
    }

    /**
     * {@inheritDoc}
     */
    public function exists(string $path, string $disk = 'public'): bool
    {
        return $this->filesystem->disk($disk)->exists($path);
    }

    /**
     * {@inheritDoc}
     */
    public function delete(string $path, string $disk = 'public'): bool
    {
        return $this->filesystem->disk($disk)->delete($path);
    }

    /**
     * {@inheritDoc}
     */
    public function path(string $path, string $disk = 'public'): string
    {
        return $this->filesystem->disk($disk)->path($path);
    }
}
