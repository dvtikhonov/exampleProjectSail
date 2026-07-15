<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Rules\MinImageDimensions;
use App\Rules\ValidDishPhotoMime;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use Tests\Support\DishPhotoTestImageFactory;
use Tests\TestCase;

class MinImageDimensionsTest extends TestCase
{
    /** Принимает изображение ровно на минимуме размеров. */
    public function test_accepts_image_at_minimum_dimensions(): void
    {
        $file = DishPhotoTestImageFactory::jpeg(800, 600);

        $validator = Validator::make(
            ['photo' => $file],
            ['photo' => [new MinImageDimensions]],
        );

        $this->assertTrue($validator->passes());
    }

    /** Отклоняет изображение с шириной ниже минимума. */
    public function test_rejects_image_below_minimum_width(): void
    {
        $file = DishPhotoTestImageFactory::jpeg(799, 600);

        $validator = Validator::make(
            ['photo' => $file],
            ['photo' => [new MinImageDimensions]],
        );

        $this->assertFalse($validator->passes());
        $this->assertStringContainsString('800×600', (string) $validator->errors()->first('photo'));
    }

    /** Отклоняет изображение с высотой ниже минимума. */
    public function test_rejects_image_below_minimum_height(): void
    {
        $file = DishPhotoTestImageFactory::jpeg(800, 599);

        $validator = Validator::make(
            ['photo' => $file],
            ['photo' => [new MinImageDimensions]],
        );

        $this->assertFalse($validator->passes());
        $this->assertStringContainsString('800×600', (string) $validator->errors()->first('photo'));
    }

    /** Валидный MIME фото блюда принимает настоящий JPEG. */
    public function test_valid_dish_photo_mime_accepts_real_jpeg(): void
    {
        $file = DishPhotoTestImageFactory::jpeg(800, 600);

        $validator = Validator::make(
            ['photo' => $file],
            ['photo' => [new ValidDishPhotoMime]],
        );

        $this->assertTrue($validator->passes());
    }

    /** Валидный MIME фото блюда отклоняет поддельное расширение. */
    public function test_valid_dish_photo_mime_rejects_fake_extension(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'fake_png_');
        file_put_contents($path, 'not-an-image');
        $file = new UploadedFile($path, 'fake.png', 'image/png', null, true);

        $validator = Validator::make(
            ['photo' => $file],
            ['photo' => [new ValidDishPhotoMime]],
        );

        $this->assertFalse($validator->passes());
    }
}
