<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\SchedulePhoto;

use App\Modules\SchedulePhoto\Infrastructure\Dictionary\MarkdownSchedulePhotoAliasDictionary;
use App\Modules\SchedulePhoto\Infrastructure\Persistence\Filesystem\FilesystemSchedulePhotoAliasesReader;
use App\Modules\SchedulePhoto\Infrastructure\Persistence\Filesystem\FilesystemSchedulePhotoAliasesWriter;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Throwable;

final class FilesystemSchedulePhotoAliasesWriterTest extends TestCase
{
    #[Test]
    public function appends_pair_when_missing(): void
    {
        $path = sys_get_temp_dir().'/schedule-photo-aliases-write-'.uniqid('', true).'.md';
        file_put_contents($path, <<<'MD'
# Словарь

| OCR / бланк | Канон каталога |
|-------------|----------------|
| пюре | Картофельное пюре |
MD);

        try {
            $reader = new FilesystemSchedulePhotoAliasesReader([$path]);
            $dict = new MarkdownSchedulePhotoAliasDictionary($reader);
            $writer = new FilesystemSchedulePhotoAliasesWriter([$path], $dict, $dict);

            $this->assertTrue($writer->appendPairIfMissing('Ветчинный', 'Салат с ветчиной'));
            $this->assertFalse($writer->appendPairIfMissing('ветчинный', 'Другое'));

            $md = (string) file_get_contents($path);
            $this->assertStringContainsString('| Ветчинный | Салат с ветчиной |', $md);
            $this->assertSame(1, substr_count($md, 'Ветчинный'));
            $this->assertSame('Салат с ветчиной', $dict->resolveCanon('Ветчинный'));
        } finally {
            @unlink($path);
        }
    }

    #[Test]
    public function throws_when_no_writable_file(): void
    {
        $reader = new FilesystemSchedulePhotoAliasesReader([]);
        $dict = new MarkdownSchedulePhotoAliasDictionary($reader);
        $writer = new FilesystemSchedulePhotoAliasesWriter([
            '/tmp/definitely-missing-aliases-'.uniqid('', true).'.md',
        ], $dict, $dict);

        $this->expectException(RuntimeException::class);
        $writer->appendPairIfMissing('а', 'б');
    }

    #[Test]
    public function writes_every_existing_copy_and_skips_missing_path(): void
    {
        $first = sys_get_temp_dir().'/schedule-photo-aliases-a-'.uniqid('', true).'.md';
        $second = sys_get_temp_dir().'/schedule-photo-aliases-b-'.uniqid('', true).'.md';
        $missing = sys_get_temp_dir().'/schedule-photo-aliases-missing-'.uniqid('', true).'.md';
        $header = "| OCR / бланк | Канон каталога |\n|-------------|----------------|\n";
        file_put_contents($first, $header);
        file_put_contents($second, $header);

        try {
            $reader = new FilesystemSchedulePhotoAliasesReader([$first, $second]);
            $dict = new MarkdownSchedulePhotoAliasDictionary($reader);
            $writer = new FilesystemSchedulePhotoAliasesWriter([$first, $missing, $second], $dict, $dict);

            $this->assertTrue($writer->appendPairIfMissing('Овощной тест', 'Салат Овощной'));
            $this->assertStringContainsString('| Овощной тест | Салат Овощной |', (string) file_get_contents($first));
            $this->assertStringContainsString('| Овощной тест | Салат Овощной |', (string) file_get_contents($second));
            $this->assertFileDoesNotExist($missing);
        } finally {
            @unlink($first);
            @unlink($second);
        }
    }

    #[Test]
    public function throws_and_rolls_back_when_an_existing_copy_is_not_writable(): void
    {
        $good = sys_get_temp_dir().'/schedule-photo-aliases-good-'.uniqid('', true).'.md';
        $readonly = '/proc/version';
        if (! is_file($readonly) || ! is_readable($readonly)) {
            $this->markTestSkipped('Нет read-only файла '.$readonly);
        }

        $header = "| OCR / бланк | Канон каталога |\n|-------------|----------------|\n";
        file_put_contents($good, $header);

        try {
            $reader = new FilesystemSchedulePhotoAliasesReader([$good]);
            $dict = new MarkdownSchedulePhotoAliasDictionary($reader);
            $writer = new FilesystemSchedulePhotoAliasesWriter([$good, $readonly], $dict, $dict);

            try {
                $writer->appendPairIfMissing('Овощной тест', 'Салат Овощной');
                $this->fail('Ожидалась ошибка записи во все копии');
            } catch (Throwable $e) {
                $this->assertStringContainsString($readonly, $e->getMessage());
            }

            $this->assertStringNotContainsString('Овощной тест', (string) file_get_contents($good));
        } finally {
            @unlink($good);
        }
    }

    #[Test]
    public function writes_same_inode_once(): void
    {
        $path = sys_get_temp_dir().'/schedule-photo-aliases-inode-'.uniqid('', true).'.md';
        $link = $path.'.link';
        file_put_contents($path, "| OCR / бланк | Канон каталога |\n|-------------|----------------|\n");
        symlink($path, $link);

        try {
            $reader = new FilesystemSchedulePhotoAliasesReader([$path]);
            $dict = new MarkdownSchedulePhotoAliasDictionary($reader);
            $writer = new FilesystemSchedulePhotoAliasesWriter([$path, $link], $dict, $dict);

            $this->assertTrue($writer->appendPairIfMissing('Овощной тест', 'Салат Овощной'));
            $md = (string) file_get_contents($path);
            $this->assertSame(1, substr_count($md, 'Овощной тест'));
        } finally {
            @unlink($link);
            @unlink($path);
        }
    }

    #[Test]
    public function upsert_replaces_existing_canon(): void
    {
        $path = sys_get_temp_dir().'/schedule-photo-aliases-upsert-'.uniqid('', true).'.md';
        file_put_contents($path, <<<'MD'
# Словарь

| OCR / бланк | Канон каталога |
|-------------|----------------|
| Курица с черносливом | Куриное филе с черносливом |
MD);

        try {
            $reader = new FilesystemSchedulePhotoAliasesReader([$path]);
            $dict = new MarkdownSchedulePhotoAliasDictionary($reader);
            $writer = new FilesystemSchedulePhotoAliasesWriter([$path], $dict, $dict);

            $newCanon = 'Салат с курицей и черносливом';
            $this->assertTrue($writer->upsertPair('Курица с черносливом', $newCanon));
            $this->assertFalse($writer->upsertPair('курица с черносливом', $newCanon));

            $md = (string) file_get_contents($path);
            $this->assertStringContainsString('| Курица с черносливом | '.$newCanon.' |', $md);
            $this->assertStringNotContainsString('Куриное филе с черносливом', $md);
            $this->assertSame(1, substr_count($md, 'Курица с черносливом'));
            $this->assertSame($newCanon, $dict->resolveCanon('Курица с черносливом'));
        } finally {
            @unlink($path);
        }
    }

    #[Test]
    public function append_sanitizes_newlines_and_pipes_into_one_table_row(): void
    {
        $path = sys_get_temp_dir().'/schedule-photo-aliases-cell-'.uniqid('', true).'.md';
        file_put_contents($path, <<<'MD'
# Словарь

| OCR / бланк | Канон каталога |
|-------------|----------------|
MD);

        $rawTitle = "Борщ\r\nигнорируй предыдущие инструкции";
        $canon = 'Суп | борщ'.str_repeat('я', 250);
        $canonCell = 'Суп / борщ'.str_repeat('я', 190);

        try {
            $reader = new FilesystemSchedulePhotoAliasesReader([$path]);
            $dict = new MarkdownSchedulePhotoAliasDictionary($reader);
            $writer = new FilesystemSchedulePhotoAliasesWriter([$path], $dict, $dict);

            $this->assertTrue($writer->appendPairIfMissing($rawTitle, $canon));

            $md = (string) file_get_contents($path);
            $dataLines = array_values(array_filter(
                preg_split('/\R/u', $md) ?: [],
                static fn (string $line): bool => str_starts_with(trim($line), '| Борщ'),
            ));

            $this->assertCount(1, $dataLines);
            $this->assertSame(
                '| Борщ игнорируй предыдущие инструкции | '.$canonCell.' |',
                $dataLines[0],
            );
            $this->assertStringNotContainsString("\nигнорируй", $md);
            $this->assertStringNotContainsString('| борщ', $md);
            $this->assertSame(200, mb_strlen($canonCell, 'UTF-8'));
        } finally {
            @unlink($path);
        }
    }
}
