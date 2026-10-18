<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\SchedulePhoto;

use App\Modules\SchedulePhoto\Application\Port\SchedulePhotoAliasDictionaryInterface;
use App\Modules\SchedulePhoto\Application\Contract\SchedulePhotoCatalogScopeBuilderInterface;
use App\Modules\SchedulePhoto\Application\Dto\SchedulePhotoCatalogScopeDishDto;
use App\Modules\SchedulePhoto\Application\Dto\SchedulePhotoDiscardedRowDto;
use App\Modules\SchedulePhoto\Domain\Record\SchedulePhotoRunRecord;
use App\Modules\SchedulePhoto\Domain\Enum\SchedulePhotoRunStatus;
use App\Modules\SchedulePhoto\Domain\Exception\SchedulePhotoDomainException;
use App\Modules\SchedulePhoto\Interface\Http\Mappers\SchedulePhotoRunAdminMapper;
use App\Modules\SchedulePhoto\Application\UseCase\Admin\SchedulePhotoAdminViewBuilder;
use App\Modules\SchedulePhoto\Domain\Service\SchedulePhotoScopeDishLookup;
use App\Modules\SchedulePhoto\Domain\Service\SchedulePhotoStoredMatchEntries;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class SchedulePhotoAdminViewBuilderTest extends TestCase
{
    #[Test]
    public function build_exposes_catalog_error_instead_of_empty_scope(): void
    {
        $catalog = $this->createMock(SchedulePhotoCatalogScopeBuilderInterface::class);
        $catalog->expects($this->once())
            ->method('build')
            ->with('http://localhost:8083', 1, [2])
            ->willThrowException(new SchedulePhotoDomainException('Ресторан не найден на выбранном сервере.', 422));

        $aliases = $this->createMock(SchedulePhotoAliasDictionaryInterface::class);
        $aliases->method('resolveCanon')->with('пюрешка')->willReturn('Пюре');

        $builder = $this->makeBuilder($catalog, $aliases);
        $mapper = new SchedulePhotoRunAdminMapper;

        $payload = $mapper->toAdminArray($builder->build($this->record(
            discarded: [
                new SchedulePhotoDiscardedRowDto(
                    rawTitle: 'пюрешка',
                    reason: 'dish_not_found',
                    dates: ['2026-08-13'],
                ),
            ],
        )));

        $this->assertSame('Ресторан не найден на выбранном сервере.', $payload['catalog_error']);
        $this->assertNull($payload['catalog_scope']);
        $this->assertSame('Пюре', $payload['discarded'][0]['suggested_canon']);
        $this->assertFalse($payload['discarded'][0]['suggested_in_scope']);
        $this->assertFalse($payload['discarded'][0]['alias_missing']);
        $this->assertSame(['2026-08-13'], $payload['discarded'][0]['dates']);
    }

    #[Test]
    public function build_returns_scope_and_suggested_canon_in_scope(): void
    {
        $catalog = $this->createMock(SchedulePhotoCatalogScopeBuilderInterface::class);
        $catalog->method('build')->willReturn([
            new SchedulePhotoCatalogScopeDishDto(
                id: 1,
                name: 'Пюре',
                categoryId: 2,
                categoryName: 'Гарнир',
            ),
        ]);

        $aliases = $this->createMock(SchedulePhotoAliasDictionaryInterface::class);
        $aliases->method('resolveCanon')->willReturn('пюре');
        $aliases->method('normalizeKey')->willReturnCallback(
            static fn (string $value): string => mb_strtolower(trim($value)),
        );

        $builder = $this->makeBuilder($catalog, $aliases);
        $mapper = new SchedulePhotoRunAdminMapper;

        $payload = $mapper->toAdminArray($builder->build($this->record(
            discarded: [
                new SchedulePhotoDiscardedRowDto(
                    rawTitle: 'пюрешка',
                    reason: 'dish_not_found',
                ),
            ],
        )));

        $this->assertNull($payload['catalog_error']);
        $this->assertSame([
            [
                'id' => 1,
                'name' => 'Пюре',
                'category_id' => 2,
                'category_name' => 'Гарнир',
            ],
        ], $payload['catalog_scope']);
        $this->assertTrue($payload['discarded'][0]['suggested_in_scope']);
        $this->assertSame('Пюре', $payload['discarded'][0]['suggested_canon']);
        $this->assertFalse($payload['discarded'][0]['alias_missing']);
        $this->assertSame([], $payload['discarded'][0]['dates']);
    }

    #[Test]
    public function build_marks_alias_missing_when_dictionary_has_no_canon(): void
    {
        $catalog = $this->createMock(SchedulePhotoCatalogScopeBuilderInterface::class);
        $catalog->method('build')->willReturn([
            new SchedulePhotoCatalogScopeDishDto(
                id: 1,
                name: 'Пюре',
                categoryId: 2,
                categoryName: 'Гарнир',
            ),
        ]);

        $aliases = $this->createMock(SchedulePhotoAliasDictionaryInterface::class);
        $aliases->method('resolveCanon')->willReturn(null);
        $aliases->expects($this->never())->method('normalizeKey');

        $builder = $this->makeBuilder($catalog, $aliases);
        $mapper = new SchedulePhotoRunAdminMapper;

        $payload = $mapper->toAdminArray($builder->build($this->record(
            discarded: [
                new SchedulePhotoDiscardedRowDto(
                    rawTitle: '  неизвестно  ',
                    reason: 'dish_not_found',
                ),
            ],
        )));

        $this->assertNull($payload['discarded'][0]['suggested_canon']);
        $this->assertFalse($payload['discarded'][0]['suggested_in_scope']);
        $this->assertTrue($payload['discarded'][0]['alias_missing']);
        $this->assertSame('неизвестно', $payload['discarded'][0]['raw_title']);
    }

    private function makeBuilder(
        SchedulePhotoCatalogScopeBuilderInterface $catalog,
        SchedulePhotoAliasDictionaryInterface $aliases,
    ): SchedulePhotoAdminViewBuilder {
        return new SchedulePhotoAdminViewBuilder(
            $catalog,
            $aliases,
            new SchedulePhotoScopeDishLookup($aliases),
            new SchedulePhotoStoredMatchEntries,
        );
    }

    /**
     * @param  list<SchedulePhotoDiscardedRowDto>  $discarded
     */
    private function record(array $discarded): SchedulePhotoRunRecord
    {
        return new SchedulePhotoRunRecord(
            id: '44444444-4444-4444-8444-444444444444',
            status: SchedulePhotoRunStatus::Matched,
            baseUrl: 'http://localhost:8083',
            dateFrom: '2026-08-13',
            dateTo: '2026-08-19',
            restaurantId: 1,
            categoryIds: [2],
            createdByMaxUserId: 42,
            photoPath: '/tmp/photo.jpg',
            discarded: $discarded,
        );
    }
}
