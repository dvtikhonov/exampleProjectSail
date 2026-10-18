<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\SchedulePhoto;

use App\Modules\SchedulePhoto\Interface\Http\Requests\StartSchedulePhotoRunRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Узкая проверка MIME правила photo: JPEG и PNG проходят, BMP и SVG — нет.
 *
 * Размер и dimensions не являются предметом этих кейсов: файлы 1×1 и меньше лимита.
 */
final class StartSchedulePhotoRunRequestTest extends TestCase
{
    /** @var list<string> */
    private array $tempFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }

        parent::tearDown();
    }

    #[DataProvider('acceptedPhotosProvider')]
    public function test_accepts_jpeg_and_png(string $bytes, string $filename, string $clientMime): void
    {
        $validator = Validator::make(
            ['photo' => $this->uploaded($bytes, $filename, $clientMime)],
            ['photo' => $this->photoRules()],
        );

        $this->assertTrue($validator->passes(), (string) $validator->errors()->first('photo'));
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: string}>
     */
    public static function acceptedPhotosProvider(): array
    {
        return [
            'jpeg' => [self::jpegBytes(), 'schedule.jpg', 'image/jpeg'],
            'png' => [self::pngBytes(), 'schedule.png', 'image/png'],
        ];
    }

    #[DataProvider('rejectedPhotosProvider')]
    public function test_rejects_bmp_and_svg(string $bytes, string $filename, string $clientMime): void
    {
        $validator = Validator::make(
            ['photo' => $this->uploaded($bytes, $filename, $clientMime)],
            ['photo' => $this->photoRules()],
        );

        $this->assertFalse($validator->passes());
        $this->assertTrue($validator->errors()->has('photo'));
    }

    /**
     * Имя и клиентский MIME выдают файл за JPEG. Содержимое — BMP или SVG.
     *
     * @return array<string, array{0: string, 1: string, 2: string}>
     */
    public static function rejectedPhotosProvider(): array
    {
        return [
            'bmp' => [self::bmpBytes(), 'schedule.jpg', 'image/jpeg'],
            'svg' => [self::svgBytes(), 'schedule.jpg', 'image/jpeg'],
        ];
    }

    /**
     * @return array<int, mixed>
     */
    private function photoRules(): array
    {
        $rules = (new StartSchedulePhotoRunRequest)->rules();

        return $rules['photo'];
    }

    private function uploaded(string $bytes, string $filename, string $clientMime): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'schedule_photo_rule_');
        if ($path === false) {
            $this->fail('Не удалось создать временный файл фото.');
        }

        file_put_contents($path, $bytes);
        $this->tempFiles[] = $path;

        return new UploadedFile($path, $filename, $clientMime, null, true);
    }

    /**
     * Минимальный JPEG 1×1: finfo → image/jpeg, getimagesize читает размеры.
     */
    private static function jpegBytes(): string
    {
        $bytes = base64_decode(
            '/9j/4AAQSkZJRgABAQEASABIAAD/2wBDAP//////////////////////////////////////////////////////////////////////////////////////wgALCAABAAEBAREA/8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQABPxA=',
            true,
        );
        self::assertIsString($bytes);

        return $bytes;
    }

    /**
     * Минимальный PNG 1×1: finfo → image/png.
     */
    private static function pngBytes(): string
    {
        $bytes = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==',
            true,
        );
        self::assertIsString($bytes);

        return $bytes;
    }

    /**
     * Минимальный BMP 1×1: finfo → image/bmp.
     */
    private static function bmpBytes(): string
    {
        $fileHeader = pack('vVvvV', 0x4D42, 58, 0, 0, 54);
        $infoHeader = pack('VVVvvVVVVVV', 40, 1, 1, 1, 24, 0, 4, 0, 0, 0, 0);

        return $fileHeader.$infoHeader."\xFF\xFF\xFF\x00";
    }

    /**
     * Минимальный SVG: finfo → image/svg+xml.
     *
     * Правило dimensions для этого MIME возвращает true без замера,
     * поэтому отказ возможен только через mimes и mimetypes.
     */
    private static function svgBytes(): string
    {
        return '<svg xmlns="http://www.w3.org/2000/svg" width="1" height="1"></svg>';
    }
}
