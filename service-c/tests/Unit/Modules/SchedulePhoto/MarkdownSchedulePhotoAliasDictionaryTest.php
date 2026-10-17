<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\SchedulePhoto;

use App\Modules\SchedulePhoto\Contracts\SchedulePhotoAliasesReaderInterface;
use App\Modules\SchedulePhoto\Infrastructure\MarkdownSchedulePhotoAliasDictionary;
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
}
