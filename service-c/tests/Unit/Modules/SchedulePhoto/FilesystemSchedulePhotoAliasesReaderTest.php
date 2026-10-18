<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\SchedulePhoto;

use App\Modules\SchedulePhoto\Infrastructure\Persistence\Filesystem\FilesystemSchedulePhotoAliasesReader;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class FilesystemSchedulePhotoAliasesReaderTest extends TestCase
{
    #[Test]
    public function reads_first_existing_file(): void
    {
        $path = sys_get_temp_dir().'/schedule-photo-aliases-'.uniqid('', true).'.md';
        file_put_contents($path, "| OCR | Канон |\n| пюре | Картофельное пюре |\n");

        try {
            $reader = new FilesystemSchedulePhotoAliasesReader([
                '/tmp/missing-aliases.md',
                $path,
            ]);
            $md = $reader->readMarkdown();
            $this->assertStringContainsString('Картофельное пюре', $md);
        } finally {
            @unlink($path);
        }
    }

    #[Test]
    public function returns_empty_when_missing(): void
    {
        $reader = new FilesystemSchedulePhotoAliasesReader([
            '/tmp/definitely-missing-schedule-photo-aliases-'.uniqid('', true).'.md',
        ]);
        $this->assertSame('', $reader->readMarkdown());
    }

    #[Test]
    public function skips_empty_file_and_uses_next(): void
    {
        $empty = sys_get_temp_dir().'/schedule-photo-aliases-empty-'.uniqid('', true).'.md';
        $filled = sys_get_temp_dir().'/schedule-photo-aliases-filled-'.uniqid('', true).'.md';
        file_put_contents($empty, "   \n");
        file_put_contents($filled, "| OCR | Канон |\n| рис | Рис |\n");

        try {
            $reader = new FilesystemSchedulePhotoAliasesReader([$empty, $filled]);
            $this->assertStringContainsString('| рис | Рис |', $reader->readMarkdown());
        } finally {
            @unlink($empty);
            @unlink($filled);
        }
    }
}
