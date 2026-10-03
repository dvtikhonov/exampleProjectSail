<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\DTO\Food\BrisklySync\BrisklyCreatedItemDto;
use App\DTO\Food\BrisklySync\BrisklySyncApplyReportDto;
use App\Exceptions\Food\FoodDomainException;
use PHPUnit\Framework\TestCase;

/**
 * DTO ответа create и apply_report.created_items.
 */
final class BrisklyCreatedItemAndApplyReportDtoTest extends TestCase
{
    public function test_created_item_from_array_maps_minimal_fields(): void
    {
        $dto = BrisklyCreatedItemDto::fromArray([
            'id' => 555001,
            'name' => 'Сельдь под шубой',
            'price' => 65,
            'barcode' => '2000033437482',
            'category_id' => 48138,
            'catalog_id' => 12,
            'status' => 1,
            'unit_id' => 796,
            'category' => ['id' => 48138, 'name' => 'Салаты'],
            'heating_enabled' => true,
        ]);

        $this->assertSame(555001, $dto->id);
        $this->assertSame('Сельдь под шубой', $dto->name);
        $this->assertSame('65.00', $dto->price);
        $this->assertSame('2000033437482', $dto->barcode);
        $this->assertSame(48138, $dto->categoryId);
        $this->assertSame(12, $dto->catalogId);
        $this->assertSame(1, $dto->status);
        $this->assertSame(796, $dto->unitId);
    }

    public function test_created_item_rejects_missing_or_zero_id(): void
    {
        try {
            BrisklyCreatedItemDto::fromArray(['name' => 'X']);
            $this->fail('Ожидался FoodDomainException');
        } catch (FoodDomainException $exception) {
            $this->assertSame(502, $exception->statusCode());
        }

        try {
            BrisklyCreatedItemDto::fromArray(['id' => 0, 'name' => 'X']);
            $this->fail('Ожидался FoodDomainException');
        } catch (FoodDomainException $exception) {
            $this->assertSame(502, $exception->statusCode());
        }
    }

    public function test_apply_report_serializes_created_items_with_backward_compat(): void
    {
        $legacy = BrisklySyncApplyReportDto::fromArray([
            'updated' => 1,
            'created' => 0,
            'skipped_unchecked' => 2,
            'skipped_equal' => 3,
            'errors' => [],
        ]);
        $this->assertSame([], $legacy->createdItems);
        $this->assertArrayHasKey('created_items', $legacy->toArray());
        $this->assertSame([], $legacy->toArray()['created_items']);

        $withItems = BrisklySyncApplyReportDto::fromArray([
            'updated' => 0,
            'created' => 1,
            'skipped_unchecked' => 0,
            'skipped_equal' => 0,
            'errors' => [],
            'created_items' => [[
                'line_key' => 'single:42',
                'briskly_item_id' => 900002,
                'barcode' => '2000000000001',
                'name' => 'Новое блюдо',
            ]],
        ]);

        $this->assertSame([
            [
                'line_key' => 'single:42',
                'briskly_item_id' => 900002,
                'barcode' => '2000000000001',
                'name' => 'Новое блюдо',
            ],
        ], $withItems->toArray()['created_items']);
    }
}
