<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Exceptions\Food\FoodDomainException;
use App\Services\Food\DishImageUploadService;
use App\Support\DishPhotoAllowedExtensions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\DishPhotoTestImageFactory;
use Tests\TestCase;

class DishImageUploadServiceTest extends TestCase
{
    private DishImageUploadService $service;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->service = new DishImageUploadService;
    }

    public function test_upload_stores_valid_jpeg_under_dishes_path(): void
    {
        $file = DishPhotoTestImageFactory::jpeg(800, 600);

        $path = $this->service->upload(42, $file);

        $this->assertStringStartsWith('dishes/42/', $path);
        $this->assertStringEndsWith('.jpg', $path);
        Storage::disk('public')->assertExists($path);
    }

    public function test_upload_stores_valid_png_with_png_extension(): void
    {
        $file = DishPhotoTestImageFactory::png(1920, 1080);

        $path = $this->service->upload(7, $file);

        $this->assertStringEndsWith('.png', $path);
        Storage::disk('public')->assertExists($path);
    }

    public function test_upload_rejects_small_dimensions(): void
    {
        $file = DishPhotoTestImageFactory::jpeg(799, 600);

        $this->expectException(FoodDomainException::class);
        $this->expectExceptionMessage('800×600');

        $this->service->upload(1, $file);
    }

    public function test_upload_rejects_disallowed_extension(): void
    {
        $file = DishPhotoTestImageFactory::jpeg(800, 600, 'dish.gif');

        $this->expectException(FoodDomainException::class);

        $this->service->upload(1, $file);
    }

    public function test_upload_rejects_fake_mime(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'fake_jpg_');
        file_put_contents($path, 'not-an-image');
        $file = new UploadedFile($path, 'dish.jpg', 'image/jpeg', null, true);

        $this->expectException(FoodDomainException::class);

        $this->service->upload(1, $file);
    }

    public function test_delete_if_exists_removes_file_from_public_disk(): void
    {
        Storage::disk('public')->put('dishes/1/old.jpg', 'bytes');

        $this->service->deleteIfExists('dishes/1/old.jpg');

        Storage::disk('public')->assertMissing('dishes/1/old.jpg');
    }

    public function test_delete_if_exists_ignores_remote_urls(): void
    {
        $this->service->deleteIfExists('https://example.com/photo.jpg');

        $this->addToAssertionCount(1);
    }

    public function test_dish_photo_allowed_extensions_constants(): void
    {
        $this->assertSame(25600, DishPhotoAllowedExtensions::MAX_SIZE_KILOBYTES);
        $this->assertContains('jfif', DishPhotoAllowedExtensions::EXTENSIONS);
        $this->assertSame('jpg', DishPhotoAllowedExtensions::normalizeExtension('jpeg'));
        $this->assertSame('png', DishPhotoAllowedExtensions::normalizeExtension('png'));
    }
}
