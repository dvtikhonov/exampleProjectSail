<?php

declare(strict_types=1);

namespace App\Http\Support;

use App\DTO\Shared\UploadedFileDto;
use App\Exceptions\Food\FoodDomainException;
use Illuminate\Http\UploadedFile;

/**
 * Конвертация Laravel UploadedFile → UploadedFileDto на HTTP-границе.
 */
final class UploadedFileDtoFactory
{
    private function __construct() {}

    /**
     * Создаёт DTO из загруженного файла.
     *
     * @throws FoodDomainException
     */
    public static function fromUploadedFile(UploadedFile $file): UploadedFileDto
    {
        $path = $file->getRealPath();

        if ($path === false || $path === '') {
            throw new FoodDomainException('Файл изображения недействителен.');
        }

        $size = $file->getSize();

        if ($size === false || $size < 0) {
            throw new FoodDomainException('Файл изображения недействителен.');
        }

        return new UploadedFileDto(
            path: $path,
            originalName: $file->getClientOriginalName(),
            mimeType: (string) ($file->getMimeType() ?? ''),
            size: $size,
        );
    }
}
