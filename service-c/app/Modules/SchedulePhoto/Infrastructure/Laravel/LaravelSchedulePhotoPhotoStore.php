<?php

declare(strict_types=1);

namespace App\Modules\SchedulePhoto\Infrastructure\Laravel;

use App\Contracts\Shared\FileStorageInterface;
use App\Modules\SchedulePhoto\Application\Port\SchedulePhotoPhotoStoreInterface;
use App\Modules\SchedulePhoto\Domain\Exception\SchedulePhotoDomainException;

/**
 * Сохраняет фото графика на диск Laravel (по умолчанию local, FileStorage порт).
 *
 * Один инстанс PHP и sidecar на том же хосте читают один каталог.
 * Второй инстанс подключает тот же каталог общим volume.
 */
final class LaravelSchedulePhotoPhotoStore implements SchedulePhotoPhotoStoreInterface
{
    /** @var array<string, string> */
    private const EXTENSION_BY_MIME = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    public function __construct(
        private readonly FileStorageInterface $files,
        private readonly string $disk,
        private readonly string $directory,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function storeFromLocalPath(string $runId, string $localAbsolutePath): string
    {
        if ($localAbsolutePath === '' || ! is_file($localAbsolutePath) || ! is_readable($localAbsolutePath)) {
            throw new SchedulePhotoDomainException('Файл фото графика недоступен.', 422);
        }

        $extension = $this->extensionFromFileMime($localAbsolutePath);

        $relativeDir = trim($this->directory, '/');
        $this->files->makeDirectory($relativeDir, $this->disk);

        $filename = $runId.'.'.$extension;
        $stored = $this->files->putFileAs($relativeDir, $localAbsolutePath, $filename, $this->disk);
        if ($stored === false) {
            throw new SchedulePhotoDomainException('Не удалось сохранить фото графика.', 500);
        }

        $absolute = $this->files->path($stored, $this->disk);
        $this->publishForHostSidecar($absolute);

        return $absolute;
    }

    /**
     * Расширение имени по MIME содержимого. Чужой или неопределённый тип — 422.
     *
     * @throws SchedulePhotoDomainException
     */
    private function extensionFromFileMime(string $localAbsolutePath): string
    {
        $extension = self::EXTENSION_BY_MIME[$this->detectMimeType($localAbsolutePath)] ?? null;
        if (! is_string($extension)) {
            throw new SchedulePhotoDomainException('Формат фото графика не поддерживается.', 422);
        }

        return $extension;
    }

    /**
     * MIME по байтам файла. Пустая строка, если finfo недоступен или тип не распознан.
     */
    private function detectMimeType(string $path): string
    {
        if (! class_exists(\finfo::class)) {
            return '';
        }

        try {
            $detected = (new \finfo(FILEINFO_MIME_TYPE))->file($path);
        } catch (\Throwable) {
            return '';
        }

        if (! is_string($detected) || $detected === '') {
            return '';
        }

        return strtolower($detected);
    }

    /**
     * Диск local создаёт каталог с правами private (0700, владелец www-data).
     * Sidecar на хосте читает тот же каталог от другого пользователя и должен
     * проходить в него и читать файл.
     * Права 0644/0755 (world-readable) намеренны для host-sidecar.
     */
    private function publishForHostSidecar(string $absoluteFile): void
    {
        $directory = dirname($absoluteFile);
        if (! is_dir($directory) || ! chmod($directory, 0755)) {
            throw new SchedulePhotoDomainException('Не удалось открыть каталог фото для OCR.', 500);
        }

        if (! is_file($absoluteFile) || ! chmod($absoluteFile, 0644)) {
            throw new SchedulePhotoDomainException('Не удалось открыть фото графика для OCR.', 500);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function relativeKey(string $absolutePath): string
    {
        $relative = $this->resolveRelativeKey($absolutePath);
        if ($relative === null) {
            throw new SchedulePhotoDomainException('Файл фото графика недоступен для OCR.', 422);
        }

        return $relative;
    }

    /**
     * {@inheritDoc}
     *
     * Расширение уже выбрано при сохранении. finfo здесь не вызывается.
     */
    public function mimeType(string $absolutePath): string
    {
        $extension = strtolower(pathinfo($absolutePath, PATHINFO_EXTENSION));

        return match ($extension) {
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'jpg', 'jpeg' => 'image/jpeg',
            default => 'image/jpeg',
        };
    }

    /**
     * {@inheritDoc}
     */
    public function delete(string $absolutePath): void
    {
        $relative = $this->resolveRelativeKey($absolutePath);
        if ($relative === null) {
            return;
        }

        if ($this->files->delete($relative, $this->disk)) {
            return;
        }

        if (is_file($this->files->path($relative, $this->disk))) {
            throw new SchedulePhotoDomainException('Не удалось удалить фото графика.', 500);
        }
    }

    /**
     * Относительный ключ внутри корня диска.
     * null — файла нет (для delete это успех).
     *
     * @throws SchedulePhotoDomainException путь вне хранилища или корень недоступен
     */
    private function resolveRelativeKey(string $absolutePath): ?string
    {
        if ($absolutePath === '' || str_contains($absolutePath, "\0")) {
            throw new SchedulePhotoDomainException('Файл фото графика недоступен для OCR.', 422);
        }

        $diskRoot = $this->files->path('', $this->disk);
        $rootReal = $diskRoot !== '' ? realpath($diskRoot) : false;
        if ($rootReal === false) {
            throw new SchedulePhotoDomainException('Корень хранилища фото графика недоступен.', 503);
        }

        $fileReal = realpath($absolutePath);
        if ($fileReal === false || ! is_file($fileReal)) {
            return null;
        }

        $rootPrefix = rtrim($rootReal, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;
        if (! str_starts_with($fileReal, $rootPrefix)) {
            throw new SchedulePhotoDomainException('Файл фото графика вне хранилища.', 422);
        }

        $relative = str_replace('\\', '/', substr($fileReal, strlen($rootPrefix)));
        if ($relative === '' || str_contains($relative, '..') || str_starts_with($relative, '/')) {
            throw new SchedulePhotoDomainException('Файл фото графика вне хранилища.', 422);
        }

        return $relative;
    }
}
