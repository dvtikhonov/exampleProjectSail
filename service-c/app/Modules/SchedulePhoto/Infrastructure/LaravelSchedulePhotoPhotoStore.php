<?php

declare(strict_types=1);

namespace App\Modules\SchedulePhoto\Infrastructure;

use App\Contracts\Shared\FileStorageInterface;
use App\Exceptions\Food\FoodDomainException;
use App\Modules\SchedulePhoto\Contracts\SchedulePhotoPhotoStoreInterface;

/**
 * Сохраняет фото графика на локальный диск Laravel (FileStorage порт).
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
            throw new FoodDomainException('Файл фото графика недоступен.', 422);
        }

        $extension = $this->extensionFromFileMime($localAbsolutePath);

        $relativeDir = trim($this->directory, '/');
        $this->files->makeDirectory($relativeDir, $this->disk);

        $filename = $runId.'.'.$extension;
        $stored = $this->files->putFileAs($relativeDir, $localAbsolutePath, $filename, $this->disk);
        if ($stored === false) {
            throw new FoodDomainException('Не удалось сохранить фото графика.', 500);
        }

        $absolute = $this->files->path($stored, $this->disk);
        $this->publishForHostSidecar($absolute);

        return $absolute;
    }

    /**
     * Расширение имени по MIME содержимого. Чужой или неопределённый тип — 422.
     *
     * @throws FoodDomainException
     */
    private function extensionFromFileMime(string $localAbsolutePath): string
    {
        $extension = self::EXTENSION_BY_MIME[$this->detectMimeType($localAbsolutePath)] ?? null;
        if (! is_string($extension)) {
            throw new FoodDomainException('Формат фото графика не поддерживается.', 422);
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
     */
    private function publishForHostSidecar(string $absoluteFile): void
    {
        $directory = dirname($absoluteFile);
        if (! is_dir($directory) || ! chmod($directory, 0755)) {
            throw new FoodDomainException('Не удалось открыть каталог фото для OCR.', 500);
        }

        if (! is_file($absoluteFile) || ! chmod($absoluteFile, 0644)) {
            throw new FoodDomainException('Не удалось открыть фото графика для OCR.', 500);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function relativeKey(string $absolutePath): string
    {
        if ($absolutePath === '' || str_contains($absolutePath, "\0")) {
            throw new FoodDomainException('Файл фото графика недоступен для OCR.', 422);
        }

        $diskRoot = $this->files->path('', $this->disk);
        $rootReal = $diskRoot !== '' ? realpath($diskRoot) : false;
        if ($rootReal === false) {
            throw new FoodDomainException('Корень хранилища фото графика недоступен.', 503);
        }

        $fileReal = realpath($absolutePath);
        if ($fileReal === false || ! is_file($fileReal)) {
            throw new FoodDomainException('Файл фото графика недоступен для OCR.', 422);
        }

        $rootPrefix = rtrim($rootReal, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;
        if (! str_starts_with($fileReal, $rootPrefix)) {
            throw new FoodDomainException('Файл фото графика вне хранилища.', 422);
        }

        $relative = str_replace('\\', '/', substr($fileReal, strlen($rootPrefix)));
        if ($relative === '' || str_contains($relative, '..') || str_starts_with($relative, '/')) {
            throw new FoodDomainException('Файл фото графика вне хранилища.', 422);
        }

        return $relative;
    }
}
