<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Support\DishPhotoAllowedExtensions;
use Illuminate\Http\UploadedFile;

/**
 * Генерация тестовых PNG для проверки загрузки фото блюд без расширения GD.
 */
final class DishPhotoTestImageFactory
{
    private function __construct() {}

    public static function jpeg(int $width, int $height, string $filename = 'dish.jpg'): UploadedFile
    {
        return self::fromBinary(self::pngBinary($width, $height), $filename, 'image/jpeg');
    }

    public static function png(int $width, int $height, string $filename = 'dish.png'): UploadedFile
    {
        return self::fromBinary(self::pngBinary($width, $height), $filename, 'image/png');
    }

    /**
     * Валидный PNG, превышающий лимит 25 МБ (для проверки правила max).
     */
    public static function oversizedPng(int $width = 800, int $height = 600): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'dish_photo_oversized_');

        if ($path === false) {
            throw new \RuntimeException('Failed to create temp file.');
        }

        $small = self::pngBinary($width, $height);
        $targetSize = DishPhotoAllowedExtensions::MAX_SIZE_BYTES + 1024;
        $remaining = max(0, $targetSize - strlen($small));

        file_put_contents($path, $small);

        if ($remaining > 0) {
            $handle = fopen($path, 'ab');

            if ($handle === false) {
                throw new \RuntimeException('Failed to open temp file for writing.');
            }

            $chunkSize = 1024 * 1024;
            $chunk = str_repeat("\x00", $chunkSize);

            try {
                while ($remaining > 0) {
                    if ($remaining >= $chunkSize) {
                        fwrite($handle, $chunk);
                        $remaining -= $chunkSize;

                        continue;
                    }

                    fwrite($handle, str_repeat("\x00", $remaining));
                    $remaining = 0;
                }
            } finally {
                fclose($handle);
            }
        }

        return new UploadedFile($path, 'oversized.png', 'image/png', null, true);
    }

    /**
     * Файл с расширением PNG, но без реального изображения (для проверки MIME).
     */
    public static function fakeMimePng(): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'fake_png_');

        if ($path === false) {
            throw new \RuntimeException('Failed to create temp file.');
        }

        file_put_contents($path, 'not-an-image');

        return new UploadedFile($path, 'fake.png', 'image/png', null, true);
    }

    /**
     * Файл с недопустимым расширением (GIF).
     */
    public static function disallowedGif(): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'dish_gif_');

        if ($path === false) {
            throw new \RuntimeException('Failed to create temp file.');
        }

        file_put_contents($path, 'GIF89a');

        return new UploadedFile($path, 'dish.gif', 'image/gif', null, true);
    }

    private static function fromBinary(string $binary, string $filename, string $mime): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'dish_photo_test_');

        if ($path === false) {
            throw new \RuntimeException('Failed to create temp file.');
        }

        file_put_contents($path, $binary);

        return new UploadedFile($path, $filename, $mime, null, true);
    }

    /**
     * Собирает валидный RGB PNG заданного размера.
     */
    private static function pngBinary(int $width, int $height): string
    {
        $signature = "\x89PNG\r\n\x1a\n";

        $ihdrData = pack('NNCCCCC', $width, $height, 8, 2, 0, 0, 0);
        $ihdr = self::pngChunk('IHDR', $ihdrData);

        $scanlines = '';
        $row = "\x00".str_repeat("\xFF\x00\x00", $width);

        for ($y = 0; $y < $height; $y++) {
            $scanlines .= $row;
        }

        $idat = self::pngChunk('IDAT', gzcompress($scanlines));
        $iend = self::pngChunk('IEND', '');

        return $signature.$ihdr.$idat.$iend;
    }

    private static function pngChunk(string $type, string $data): string
    {
        return pack('N', strlen($data)).$type.$data.pack('N', crc32($type.$data) & 0xFFFFFFFF);
    }
}
