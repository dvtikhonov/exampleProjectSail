<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\UploadedFile;

/**
 * Единый whitelist расширений и MIME для фото блюд (PNG/JPEG).
 */
final class DishPhotoAllowedExtensions
{
    /** @var list<string> */
    public const EXTENSIONS = [
        'png',
        'jpg',
        'jpeg',
        'jpe',
        'pjp',
        'pjpeg',
        'jfif',
    ];

    /** @var list<string> */
    public const MIME_TYPES = [
        'image/png',
        'image/jpeg',
    ];

    public const MAX_SIZE_KILOBYTES = 25600;

    public const MAX_SIZE_BYTES = self::MAX_SIZE_KILOBYTES * 1024;

    public const MIN_WIDTH = 800;

    public const MIN_HEIGHT = 600;

    private function __construct() {}

    /**
     * Строка для правила Laravel `mimes:`.
     */
    public static function mimesRule(): string
    {
        return 'mimes:'.implode(',', self::EXTENSIONS);
    }

    /**
     * Значение HTML-атрибута `accept` для input type="file".
     */
    public static function fileAcceptAttribute(): string
    {
        $extensions = array_map(
            static fn (string $extension): string => '.'.$extension,
            self::EXTENSIONS,
        );

        return implode(',', [...$extensions, 'image/png', 'image/jpeg']);
    }

    public static function isAllowedExtension(string $extension): bool
    {
        return in_array(strtolower($extension), self::EXTENSIONS, true);
    }

    public static function isAllowedMime(string $mime): bool
    {
        return in_array(strtolower($mime), self::MIME_TYPES, true);
    }

    /**
     * Нормализует расширение до `.png` или `.jpg` для хранения на диске.
     */
    public static function normalizeExtension(string $extension): string
    {
        $extension = strtolower(ltrim($extension, '.'));

        if ($extension === 'png') {
            return 'png';
        }

        return 'jpg';
    }

    /**
     * Определяет MIME-тип файла через finfo.
     */
    public static function detectMimeFromPath(string $path): ?string
    {
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($path);

        return is_string($mime) ? strtolower($mime) : null;
    }

    /**
     * Читает ширину и высоту загруженного изображения.
     *
     * @return array{width: int, height: int}|null
     */
    public static function readDimensions(UploadedFile $file): ?array
    {
        $path = $file->getRealPath();

        if ($path === false) {
            return null;
        }

        $size = @getimagesize($path);

        if ($size === false) {
            return null;
        }

        return [
            'width' => (int) $size[0],
            'height' => (int) $size[1],
        ];
    }

    public static function meetsMinDimensions(int $width, int $height): bool
    {
        return $width >= self::MIN_WIDTH && $height >= self::MIN_HEIGHT;
    }
}
