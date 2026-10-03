<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\Food\Menu\DishWeightUnit;
use App\Services\Food\BrisklySync\BrisklyCreateNameFormatter;
use PHPUnit\Framework\TestCase;

class BrisklyCreateNameFormatterTest extends TestCase
{
    public function test_single_with_weight(): void
    {
        $this->assertSame(
            'Тостер Тест, 200г',
            BrisklyCreateNameFormatter::forSingle('Тостер Тест', '200', DishWeightUnit::Gram),
        );
    }

    public function test_single_without_weight_keeps_name_only(): void
    {
        $this->assertSame(
            'Тостер Тест',
            BrisklyCreateNameFormatter::forSingle('Тостер Тест', '', DishWeightUnit::Gram),
        );
        $this->assertSame(
            'Тостер Тест',
            BrisklyCreateNameFormatter::forSingle('  Тостер Тест  ', '   ', DishWeightUnit::Liter),
        );
    }

    public function test_single_rounds_weight_and_uses_unit_label(): void
    {
        $this->assertSame(
            'Сок, 1л',
            BrisklyCreateNameFormatter::forSingle('Сок', '0.5', DishWeightUnit::Liter),
        );
        $this->assertSame(
            'Молоко, 500мл',
            BrisklyCreateNameFormatter::forSingle('Молоко', '500.4', DishWeightUnit::Milliliter),
        );
        $this->assertSame(
            'Стейк, 1кг',
            BrisklyCreateNameFormatter::forSingle('Стейк', '1.2', DishWeightUnit::Kilogram),
        );
    }

    public function test_combo_with_weights(): void
    {
        $this->assertSame(
            'Суп, 300г / Салат, 150г',
            BrisklyCreateNameFormatter::forCombo(
                'Суп',
                '300',
                DishWeightUnit::Gram,
                'Салат',
                '150',
                DishWeightUnit::Gram,
            ),
        );
    }

    public function test_combo_mixed_and_missing_weights(): void
    {
        $this->assertSame(
            'Суп, 300г / Салат',
            BrisklyCreateNameFormatter::forCombo(
                'Суп',
                '300',
                DishWeightUnit::Gram,
                'Салат',
                '',
                DishWeightUnit::Gram,
            ),
        );
        $this->assertSame(
            'Суп / Салат',
            BrisklyCreateNameFormatter::forCombo(
                'Суп',
                '',
                DishWeightUnit::Gram,
                'Салат',
                '',
                DishWeightUnit::Gram,
            ),
        );
    }

    public function test_weight_label_null_when_empty(): void
    {
        $this->assertNull(BrisklyCreateNameFormatter::weightLabel('', DishWeightUnit::Gram));
        $this->assertSame('200г', BrisklyCreateNameFormatter::weightLabel('200', DishWeightUnit::Gram));
    }
}
