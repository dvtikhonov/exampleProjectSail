<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\SchedulePhoto;

use App\Contracts\Shared\FileStorageInterface;
use App\Modules\SchedulePhoto\Domain\Exception\SchedulePhotoDomainException;
use App\Modules\SchedulePhoto\Infrastructure\Laravel\LaravelSchedulePhotoPhotoStore;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class LaravelSchedulePhotoPhotoStoreTest extends TestCase
{
    private string $root = '';

    protected function tearDown(): void
    {
        if ($this->root !== '' && is_dir($this->root)) {
            $this->removeTree($this->root);
        }

        parent::tearDown();
    }

    #[Test]
    public function relative_key_is_inside_disk_root(): void
    {
        $this->root = $this->makeRoot();
        $dir = $this->root.'/schedule-photo';
        mkdir($dir);
        $file = $dir.'/run.jpg';
        file_put_contents($file, 'img');

        $key = $this->store($this->root)->relativeKey($file);

        $this->assertSame('schedule-photo/run.jpg', $key);
    }

    #[Test]
    public function relative_key_rejects_path_outside_disk_root(): void
    {
        $this->root = $this->makeRoot();
        $outside = sys_get_temp_dir().'/schedule-photo-outside-'.uniqid('', true).'.jpg';
        file_put_contents($outside, 'secret');

        try {
            $this->store($this->root)->relativeKey($outside);
            $this->fail('Expected SchedulePhotoDomainException');
        } catch (SchedulePhotoDomainException $exception) {
            $this->assertSame(422, $exception->statusCode());
        } finally {
            @unlink($outside);
        }
    }

    #[Test]
    public function relative_key_rejects_symlink_that_leaves_disk_root(): void
    {
        $this->root = $this->makeRoot();
        $outsideDir = sys_get_temp_dir().'/schedule-photo-link-out-'.uniqid('', true);
        mkdir($outsideDir);
        $outside = $outsideDir.'/secret.jpg';
        file_put_contents($outside, 'secret');

        $dir = $this->root.'/schedule-photo';
        mkdir($dir);
        $link = $dir.'/link.jpg';
        $this->assertTrue(symlink($outside, $link));

        try {
            $this->store($this->root)->relativeKey($link);
            $this->fail('Expected SchedulePhotoDomainException');
        } catch (SchedulePhotoDomainException $exception) {
            $this->assertSame(422, $exception->statusCode());
        } finally {
            $this->removeTree($outsideDir);
        }
    }

    #[Test]
    public function relative_key_rejects_parent_segment_that_leaves_disk_root(): void
    {
        $this->root = $this->makeRoot();
        $outside = dirname($this->root).'/escaped.jpg';
        file_put_contents($outside, 'secret');

        try {
            $this->store($this->root)->relativeKey($this->root.'/../'.basename($outside));
            $this->fail('Expected SchedulePhotoDomainException');
        } catch (SchedulePhotoDomainException $exception) {
            $this->assertSame(422, $exception->statusCode());
        } finally {
            @unlink($outside);
        }
    }

    #[Test]
    public function stores_extensionless_png_as_png(): void
    {
        $this->root = $this->makeRoot();
        $source = $this->tempFile($this->pngBytes());

        try {
            $absolute = $this->store($this->root)->storeFromLocalPath('run-42', $source);

            $this->assertSame($this->root.'/schedule-photo/run-42.png', $absolute);
            $this->assertFileExists($absolute);
            $this->assertSame($this->pngBytes(), (string) file_get_contents($absolute));
        } finally {
            @unlink($source);
        }
    }

    #[Test]
    public function mime_type_follows_extension_of_stored_file(): void
    {
        $this->root = $this->makeRoot();
        $store = $this->store($this->root);
        $dir = $this->root.'/schedule-photo';

        $this->assertSame('image/jpeg', $store->mimeType($dir.'/run.jpg'));
        $this->assertSame('image/jpeg', $store->mimeType($dir.'/run.jpeg'));
        $this->assertSame('image/png', $store->mimeType($dir.'/run.png'));
        $this->assertSame('image/webp', $store->mimeType($dir.'/run.webp'));
        $this->assertSame('image/jpeg', $store->mimeType($dir.'/run.bmp'));
    }

    #[Test]
    public function delete_removes_file_inside_disk_root(): void
    {
        $this->root = $this->makeRoot();
        $dir = $this->root.'/schedule-photo';
        mkdir($dir);
        $file = $dir.'/run.jpg';
        file_put_contents($file, 'img');

        $this->store($this->root)->delete($file);

        $this->assertFileDoesNotExist($file);
    }

    #[Test]
    public function delete_missing_file_is_success(): void
    {
        $this->root = $this->makeRoot();

        $this->store($this->root)->delete($this->root.'/schedule-photo/missing.jpg');

        $this->addToAssertionCount(1);
    }

    #[Test]
    public function delete_rejects_path_outside_disk_root(): void
    {
        $this->root = $this->makeRoot();
        $outside = sys_get_temp_dir().'/schedule-photo-delete-out-'.uniqid('', true).'.jpg';
        file_put_contents($outside, 'secret');

        try {
            $this->store($this->root)->delete($outside);
            $this->fail('Expected SchedulePhotoDomainException');
        } catch (SchedulePhotoDomainException $exception) {
            $this->assertSame(422, $exception->statusCode());
            $this->assertFileExists($outside);
        } finally {
            @unlink($outside);
        }
    }

    #[Test]
    public function delete_throws_when_storage_keeps_the_file(): void
    {
        $this->root = $this->makeRoot();
        $dir = $this->root.'/schedule-photo';
        mkdir($dir);
        $file = $dir.'/run.jpg';
        file_put_contents($file, 'img');

        try {
            $this->store($this->root, false)->delete($file);
            $this->fail('Expected SchedulePhotoDomainException');
        } catch (SchedulePhotoDomainException $exception) {
            $this->assertSame(500, $exception->statusCode());
            $this->assertFileExists($file);
        }
    }

    #[Test]
    public function rejects_bmp_without_writing_a_file(): void
    {
        $this->root = $this->makeRoot();
        $source = $this->tempFile($this->bmpBytes());

        try {
            $this->store($this->root)->storeFromLocalPath('run-bmp', $source);
            $this->fail('Expected SchedulePhotoDomainException');
        } catch (SchedulePhotoDomainException $exception) {
            $this->assertSame(422, $exception->statusCode());
        } finally {
            @unlink($source);
        }

        $storedDir = $this->root.'/schedule-photo';
        if (is_dir($storedDir)) {
            $entries = array_diff(scandir($storedDir) ?: [], ['.', '..']);
            $this->assertSame([], array_values($entries));
        }
    }

    #[Test]
    public function relative_key_rejects_empty_disk_root(): void
    {
        $store = $this->store('');

        try {
            $store->relativeKey('/tmp/photo.jpg');
            $this->fail('Expected SchedulePhotoDomainException');
        } catch (SchedulePhotoDomainException $exception) {
            $this->assertSame(503, $exception->statusCode());
        }
    }

    private function store(string $root, bool $allowDelete = true): LaravelSchedulePhotoPhotoStore
    {
        return new LaravelSchedulePhotoPhotoStore(
            files: new class($root, $allowDelete) implements FileStorageInterface
            {
                public function __construct(
                    private readonly string $root,
                    private readonly bool $allowDelete = true,
                ) {}

                public function makeDirectory(string $path, string $disk = 'public'): bool
                {
                    if ($this->root === '') {
                        return false;
                    }

                    $absolute = $this->path($path, $disk);
                    if (is_dir($absolute)) {
                        return true;
                    }

                    return mkdir($absolute, 0775, true);
                }

                public function put(string $path, string $contents, string $disk = 'public'): bool
                {
                    return false;
                }

                public function putFileAs(
                    string $directory,
                    string $localPath,
                    string $filename,
                    string $disk = 'public',
                ): string|false {
                    if ($this->root === '') {
                        return false;
                    }

                    $relative = trim($directory, '/').'/'.$filename;
                    $target = $this->path($relative, $disk);
                    $parent = dirname($target);
                    if (! is_dir($parent) && ! mkdir($parent, 0775, true)) {
                        return false;
                    }

                    if (! is_file($localPath) || ! copy($localPath, $target)) {
                        return false;
                    }

                    return $relative;
                }

                public function exists(string $path, string $disk = 'public'): bool
                {
                    return false;
                }

                public function delete(string $path, string $disk = 'public'): bool
                {
                    if ($this->root === '' || ! $this->allowDelete) {
                        return false;
                    }

                    $absolute = $this->path($path, $disk);
                    if (! is_file($absolute)) {
                        return false;
                    }

                    return unlink($absolute);
                }

                public function path(string $path, string $disk = 'public'): string
                {
                    if ($this->root === '') {
                        return '';
                    }

                    if ($path === '' || $path === '.') {
                        return $this->root;
                    }

                    return $this->root.'/'.ltrim($path, '/');
                }
            },
            disk: 'local',
            directory: 'schedule-photo',
        );
    }

    private function makeRoot(): string
    {
        $root = sys_get_temp_dir().'/schedule-photo-root-'.uniqid('', true);
        mkdir($root);

        return $root;
    }

    private function tempFile(string $bytes): string
    {
        $path = sys_get_temp_dir().'/schedule-photo-src-'.uniqid('', true);
        file_put_contents($path, $bytes);

        return $path;
    }

    /**
     * Минимальный PNG 1×1, который finfo определяет как image/png.
     */
    private function pngBytes(): string
    {
        $bytes = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==',
            true,
        );
        $this->assertIsString($bytes);

        return $bytes;
    }

    /**
     * Минимальный BMP 1×1. finfo не относит его к JPEG/PNG/WebP.
     */
    private function bmpBytes(): string
    {
        $fileHeader = pack('vVvvV', 0x4D42, 58, 0, 0, 54);
        $infoHeader = pack('VVVvvVVVVVV', 40, 1, 1, 1, 24, 0, 4, 0, 0, 0, 0);

        return $fileHeader.$infoHeader."\xFF\xFF\xFF\x00";
    }

    private function removeTree(string $path): void
    {
        if (is_link($path) || is_file($path)) {
            @unlink($path);

            return;
        }

        if (! is_dir($path)) {
            return;
        }

        $items = scandir($path);
        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $this->removeTree($path.'/'.$item);
        }

        @rmdir($path);
    }
}
