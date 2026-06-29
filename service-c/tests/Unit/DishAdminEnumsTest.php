<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\Food\DishVatRate;
use App\Enums\Food\DishWeightUnit;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class DishAdminEnumsTest extends TestCase
{
    public function test_weight_unit_labels(): void
    {
        $this->assertSame('г', DishWeightUnit::Gram->label());
        $this->assertSame('кг', DishWeightUnit::Kilogram->label());
        $this->assertSame('мл', DishWeightUnit::Milliliter->label());
        $this->assertSame('л', DishWeightUnit::Liter->label());
    }

    #[DataProvider('vatRateProvider')]
    public function test_vat_rate_values_and_labels(?int $dbValue, DishVatRate $expected, string $label): void
    {
        $this->assertSame($expected, DishVatRate::fromValue($dbValue));
        $this->assertSame($dbValue, $expected->value());
        $this->assertSame($label, $expected->label());
    }

    /**
     * @return array<string, array{0: int|null, 1: DishVatRate, 2: string}>
     */
    public static function vatRateProvider(): array
    {
        return [
            'exempt' => [null, DishVatRate::Exempt, 'Не облагается НДС'],
            'five' => [5, DishVatRate::Five, '5%'],
            'seven' => [7, DishVatRate::Seven, '7%'],
            'ten' => [10, DishVatRate::Ten, '10%'],
            'twenty' => [20, DishVatRate::Twenty, '20%'],
            'twenty_two' => [22, DishVatRate::TwentyTwo, '22%'],
        ];
    }
}
