<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\SchedulePhoto;

use App\Modules\SchedulePhoto\Application\Port\SchedulePhotoAliasesReaderInterface;
use App\Modules\SchedulePhoto\Infrastructure\Dictionary\MarkdownSchedulePhotoAliasDictionary;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class MarkdownSchedulePhotoAliasDictionaryTest extends TestCase
{
    #[Test]
    public function resolves_canon_from_markdown_table(): void
    {
        $reader = $this->createMock(SchedulePhotoAliasesReaderInterface::class);
        $reader->method('readMarkdown')->willReturn(<<<'MD'
| OCR / бланк | Канон каталога |
|-------------|----------------|
| Ветчинный | Салат с ветчиной (ветчина, яйцо) |
| пюре | Картофельное пюре |
MD);

        $dict = new MarkdownSchedulePhotoAliasDictionary($reader);

        $this->assertSame(
            'Салат с ветчиной (ветчина, яйцо)',
            $dict->resolveCanon('Ветчинный'),
        );
        $this->assertSame(
            'Салат с ветчиной (ветчина, яйцо)',
            $dict->resolveCanon('ветчинный'),
        );
        $this->assertSame('Картофельное пюре', $dict->resolveCanon('Пюре'));
        $this->assertNull($dict->resolveCanon('неизвестно'));
    }

    #[Test]
    public function normalize_key_strips_quotes_and_yo(): void
    {
        $reader = $this->createMock(SchedulePhotoAliasesReaderInterface::class);
        $reader->method('readMarkdown')->willReturn('');
        $dict = new MarkdownSchedulePhotoAliasDictionary($reader);

        $this->assertSame('салат овощной', $dict->normalizeKey('Салат «Овощной»'));
        $this->assertSame('пюре', $dict->normalizeKey('Пюрё'));
    }

    #[Test]
    public function clear_cache_rereads_markdown_on_next_resolve(): void
    {
        $markdown = <<<'MD'
| OCR / бланк | Канон каталога |
|-------------|----------------|
| пюре | Картофельное пюре |
MD;
        $reader = $this->createMock(SchedulePhotoAliasesReaderInterface::class);
        $reader->expects($this->exactly(2))->method('readMarkdown')->willReturn($markdown);

        $dict = new MarkdownSchedulePhotoAliasDictionary($reader);

        $this->assertSame('Картофельное пюре', $dict->resolveCanon('пюре'));
        $dict->clearCache();
        $this->assertSame('Картофельное пюре', $dict->resolveCanon('пюре'));
    }
}
