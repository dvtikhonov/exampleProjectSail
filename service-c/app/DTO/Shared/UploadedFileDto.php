<?php

declare(strict_types=1);

namespace App\DTO\Shared;

/**
 * Метаданные загруженного файла на границе домена (без Illuminate UploadedFile).
 */
readonly class UploadedFileDto
{
    public function __construct(
        public string $path,
        public string $originalName,
        public string $mimeType,
        public int $size,
    ) {}

    /**
     * Расширение из оригинального имени файла (без точки), в нижнем регистре.
     */
    public function extension(): string
    {
        return strtolower(pathinfo($this->originalName, PATHINFO_EXTENSION));
    }
}
