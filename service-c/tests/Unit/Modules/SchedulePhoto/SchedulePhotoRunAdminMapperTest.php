<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\SchedulePhoto;

use App\Modules\SchedulePhoto\Application\Dto\SchedulePhotoAdminRunViewDto;
use App\Modules\SchedulePhoto\Application\Dto\SchedulePhotoCatalogScopeDishDto;
use App\Modules\SchedulePhoto\Application\Dto\SchedulePhotoDiscardedHintDto;
use App\Modules\SchedulePhoto\Application\Dto\SchedulePhotoDiscardedRowDto;
use App\Modules\SchedulePhoto\Domain\Record\SchedulePhotoRunRecord;
use App\Modules\SchedulePhoto\Domain\Enum\SchedulePhotoRunStatus;
use App\Modules\SchedulePhoto\Interface\Http\Mappers\SchedulePhotoRunAdminMapper;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class SchedulePhotoRunAdminMapperTest extends TestCase
{
    #[Test]
    public function to_admin_array_serializes_view_dto_without_enrichment(): void
    {
        $run = new SchedulePhotoRunRecord(
            id: '44444444-4444-4444-8444-444444444444',
            status: SchedulePhotoRunStatus::Matched,
            baseUrl: 'http://localhost:8083',
            dateFrom: '2026-08-13',
            dateTo: '2026-08-19',
            restaurantId: 1,
            categoryIds: [2],
            createdByMaxUserId: 42,
            photoPath: '/tmp/photo.jpg',
            discarded: [
                new SchedulePhotoDiscardedRowDto(
                    rawTitle: 'пюрешка',
                    reason: 'dish_not_found',
                ),
            ],
        );

        $view = new SchedulePhotoAdminRunViewDto(
            run: $run,
            discarded: [
                new SchedulePhotoDiscardedHintDto(
                    rawTitle: 'пюрешка',
                    reason: 'dish_not_found',
                    dates: ['2026-08-13'],
                    suggestedCanon: 'Пюре',
                    suggestedInScope: true,
                    aliasMissing: false,
                ),
            ],
            catalogScope: [
                new SchedulePhotoCatalogScopeDishDto(
                    id: 1,
                    name: 'Пюре',
                    categoryId: 2,
                    categoryName: 'Гарнир',
                ),
            ],
            catalogError: null,
        );

        $payload = (new SchedulePhotoRunAdminMapper)->toAdminArray($view);

        $this->assertSame('44444444-4444-4444-8444-444444444444', $payload['id']);
        $this->assertSame('matched', $payload['status']);
        $this->assertNull($payload['catalog_error']);
        $this->assertSame([
            [
                'id' => 1,
                'name' => 'Пюре',
                'category_id' => 2,
                'category_name' => 'Гарнир',
            ],
        ], $payload['catalog_scope']);
        $this->assertSame([
            [
                'raw_title' => 'пюрешка',
                'reason' => 'dish_not_found',
                'dates' => ['2026-08-13'],
                'suggested_canon' => 'Пюре',
                'suggested_in_scope' => true,
                'alias_missing' => false,
            ],
        ], $payload['discarded']);
    }

    #[Test]
    public function to_admin_array_keeps_null_catalog_scope_and_error_message(): void
    {
        $run = new SchedulePhotoRunRecord(
            id: '44444444-4444-4444-8444-444444444444',
            status: SchedulePhotoRunStatus::Matched,
            baseUrl: 'http://localhost:8083',
            dateFrom: '2026-08-13',
            dateTo: '2026-08-19',
            restaurantId: 1,
            categoryIds: [2],
            createdByMaxUserId: 42,
        );

        $view = new SchedulePhotoAdminRunViewDto(
            run: $run,
            discarded: [],
            catalogScope: null,
            catalogError: 'Ресторан не найден на выбранном сервере.',
        );

        $payload = (new SchedulePhotoRunAdminMapper)->toAdminArray($view);

        $this->assertNull($payload['catalog_scope']);
        $this->assertSame('Ресторан не найден на выбранном сервере.', $payload['catalog_error']);
        $this->assertSame([], $payload['discarded']);
    }
}
