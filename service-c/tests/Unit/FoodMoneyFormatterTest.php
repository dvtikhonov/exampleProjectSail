<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Food\Shared\FoodMoneyFormatter;
use App\Support\Max\Food\Formatting\FoodOrderMaxMoneyFormatter;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class FoodMoneyFormatterTest extends TestCase
{
    /** format нормализует строковую сумму до двух знаков без float. */
    public function test_format_normalizes_string_amount_without_float(): void
    {
        $formatter = new FoodMoneyFormatter;

        $this->assertSame('0.10', $formatter->format('0.1'));
        $this->assertSame('10.00', $formatter->format(10));
        $this->assertSame('1.25', $formatter->format('1.25'));
    }

    /** toCents и formatCents не дрейфуют на классическом 0.1 + 0.2. */
    public function test_to_cents_and_format_cents_avoid_float_drift(): void
    {
        $formatter = new FoodMoneyFormatter;

        $this->assertSame(10, $formatter->toCents('0.1'));
        $this->assertSame(20, $formatter->toCents('0.2'));

        $sumCents = $formatter->toCents('0.1') + $formatter->toCents('0.2');

        $this->assertSame(30, $sumCents);
        $this->assertSame('0.30', $formatter->formatCents($sumCents));
    }

    /** toCents корректно обрабатывает отрицательные суммы. */
    public function test_to_cents_handles_negative_amounts(): void
    {
        $formatter = new FoodMoneyFormatter;

        $this->assertSame(-125, $formatter->toCents('-1.25'));
        $this->assertSame('-1.25', $formatter->formatCents(-125));
    }

    /**
     * @return array<string, array{0: mixed, 1: string}>
     */
    public static function maxMoneyAmountProvider(): array
    {
        return [
            'null' => [null, '0.00'],
            'empty string' => ['', '0.00'],
            'string tenth' => ['0.1', '0.10'],
            'integer' => [5, '5.00'],
        ];
    }

    /** formatMoneyAmount форматирует через bcmath. */
    #[DataProvider('maxMoneyAmountProvider')]
    public function test_max_format_money_amount_uses_bcmath(mixed $amount, string $expected): void
    {
        $formatter = new FoodOrderMaxMoneyFormatter;

        $this->assertSame($expected, $formatter->formatMoneyAmount($amount));
    }

    /**
     * @return array<string, array{0: mixed, 1: string}>
     */
    public static function maxRublesAmountProvider(): array
    {
        return [
            'null' => [null, '0'],
            'empty string' => ['', '0'],
            'round up' => ['10.6', '11'],
            'round down' => ['10.4', '10'],
            'half up' => ['10.5', '11'],
            'negative half' => ['-10.5', '-11'],
        ];
    }

    /** formatRublesAmount округляет через bcmath без (float). */
    #[DataProvider('maxRublesAmountProvider')]
    public function test_max_format_rubles_amount_rounds_via_bcmath(mixed $amount, string $expected): void
    {
        $formatter = new FoodOrderMaxMoneyFormatter;

        $this->assertSame($expected, $formatter->formatRublesAmount($amount));
    }
}
