<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\DTO\Food\Menu\DishRecord;
use App\Enums\Food\Menu\DishWeightUnit;
use App\Services\Food\ComboCatalog\WeightLabelCanonicalizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class WeightLabelCanonicalizerTest extends TestCase
{
    private WeightLabelCanonicalizer $canonicalizer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->canonicalizer = new WeightLabelCanonicalizer;
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function compactLabelProvider(): array
    {
        return [
            'no space' => ['120г', '120 грамм'],
            'space' => ['120 г', '120 грамм'],
            'trailing period' => ['120 г.', '120 грамм'],
            'gr alias' => ['120гр', '120 грамм'],
            'gr with period' => ['120 гр.', '120 грамм'],
            'already canon' => ['120 грамм', '120 грамм'],
            'kg compact' => ['1кг', '1 килограмм'],
            'ml space' => ['250 мл', '250 миллилитр'],
            'liter' => ['1 л', '1 литр'],
        ];
    }

    #[DataProvider('compactLabelProvider')]
    public function test_from_label_canonicalizes_compact_forms(string $raw, string $expected): void
    {
        $this->assertSame($expected, $this->canonicalizer->fromLabel($raw));
    }

    public function test_from_label_returns_null_for_unknown_unit(): void
    {
        $this->assertNull($this->canonicalizer->fromLabel('120 шт'));
        $this->assertNull($this->canonicalizer->fromLabel('без веса'));
        $this->assertNull($this->canonicalizer->fromLabel(''));
        $this->assertNull($this->canonicalizer->fromLabel(null));
    }

    public function test_from_amount_and_unit_builds_canon(): void
    {
        $this->assertSame(
            '120 грамм',
            $this->canonicalizer->fromAmountAndUnit('120', DishWeightUnit::Gram),
        );
        $this->assertSame(
            '2 килограмм',
            $this->canonicalizer->fromAmountAndUnit(2, DishWeightUnit::Kilogram),
        );
    }

    public function test_from_dish_uses_weight_and_unit(): void
    {
        $dish = new DishRecord(
            id: 1,
            menuCategoryId: 10,
            name: 'Салат',
            description: null,
            weight: '110.000',
            weightUnit: DishWeightUnit::Gram,
            imageUrl: null,
            price: '150.00',
            vatRate: null,
            isAvailable: true,
        );

        $this->assertSame('110 грамм', $this->canonicalizer->fromDish($dish));
    }

    public function test_from_dish_empty_weight_returns_null(): void
    {
        $dish = new DishRecord(
            id: 1,
            menuCategoryId: 10,
            name: 'Салат',
            description: null,
            weight: '',
            weightUnit: DishWeightUnit::Gram,
            imageUrl: null,
            price: '150.00',
            vatRate: null,
            isAvailable: true,
        );

        $this->assertNull($this->canonicalizer->fromDish($dish));
    }
}
