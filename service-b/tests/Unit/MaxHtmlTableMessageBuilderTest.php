<?php

namespace Tests\Unit;

use App\Services\Max\MaxHtmlTableMessageBuilder;
use App\Services\Max\PlainTextTableRenderer;
use Tests\TestCase;

class MaxHtmlTableMessageBuilderTest extends TestCase
{
    private MaxHtmlTableMessageBuilder $builder;

    /** @var array<int, array{key: string, label: string}> */
    private array $columns;

    protected function setUp(): void
    {
        parent::setUp();

        $this->builder = new MaxHtmlTableMessageBuilder(new PlainTextTableRenderer);
        $this->columns = [
            ['key' => 'id', 'label' => 'ID объекта продаж'],
            ['key' => 'shop', 'label' => 'Магазин'],
        ];
    }

    public function test_empty_rows_returns_intro_only_within_limit(): void
    {
        $result = $this->builder->build(
            intro: 'Объекты продаж — отчёт',
            columns: $this->columns,
            rows: [],
            maxTextLength: 4000,
        );

        $this->assertSame('Объекты продаж — отчёт', $result->text);
        $this->assertSame(0, $result->totalRows);
        $this->assertSame(0, $result->includedRows);
        $this->assertFalse($result->truncated);
        $this->assertLessThanOrEqual(4000, mb_strlen($result->text));
    }

    public function test_hundred_rows_are_truncated_with_footer(): void
    {
        $rows = [];

        for ($i = 1; $i <= 100; $i++) {
            $rows[] = [
                'id' => (string) $i,
                'shop' => sprintf('Магазин №%03d — филиал', $i),
            ];
        }

        $result = $this->builder->build(
            intro: 'Объекты продаж — отчёт',
            columns: $this->columns,
            rows: $rows,
            maxTextLength: 4000,
        );

        $this->assertTrue($result->truncated);
        $this->assertSame(100, $result->totalRows);
        $this->assertGreaterThan(0, $result->includedRows);
        $this->assertLessThan(100, $result->includedRows);
        $this->assertLessThanOrEqual(4000, mb_strlen($result->text));
        $this->assertStringContainsString(
            sprintf('Показаны первые %d из 100', $result->includedRows),
            $result->text,
        );
        $this->assertStringContainsString('CSV-экспорт на портале', $result->text);
    }

    public function test_few_rows_fit_entirely_without_truncation(): void
    {
        $rows = [
            ['id' => '1', 'shop' => 'Курск'],
            ['id' => '2', 'shop' => 'Самара'],
        ];

        $result = $this->builder->build(
            intro: 'Отчёт',
            columns: $this->columns,
            rows: $rows,
            maxTextLength: 4000,
        );

        $this->assertFalse($result->truncated);
        $this->assertSame(2, $result->totalRows);
        $this->assertSame(2, $result->includedRows);
        $this->assertLessThanOrEqual(4000, mb_strlen($result->text));
        $this->assertStringContainsString('Курск', $result->text);
        $this->assertStringContainsString('Самара', $result->text);
    }

    public function test_text_at_exact_limit_is_accepted(): void
    {
        $intro = str_repeat('а', 50);
        $shopValue = str_repeat('б', 200);
        $rows = [['id' => '1', 'shop' => $shopValue]];

        $maxLength = mb_strlen(
            $this->builder->build(intro: $intro, columns: $this->columns, rows: $rows, maxTextLength: 100_000)->text,
        );

        while ($maxLength > 4000) {
            $shopValue = mb_substr($shopValue, 0, -10);
            $rows = [['id' => '1', 'shop' => $shopValue]];
            $maxLength = mb_strlen(
                $this->builder->build(intro: $intro, columns: $this->columns, rows: $rows, maxTextLength: 100_000)->text,
            );
        }

        $result = $this->builder->build(
            intro: $intro,
            columns: $this->columns,
            rows: $rows,
            maxTextLength: 4000,
        );

        $this->assertLessThanOrEqual(4000, mb_strlen($result->text));
        $this->assertStringContainsString(' | ', $result->text);
        $this->assertStringNotContainsString('<table', $result->text);
    }

    public function test_long_cell_values_are_truncated_within_limit(): void
    {
        $longValue = str_repeat('Очень длинное название магазина ', 200);

        $result = $this->builder->build(
            intro: 'Объекты продаж — отчёт',
            columns: $this->columns,
            rows: [
                ['id' => '1', 'shop' => $longValue],
            ],
            maxTextLength: 4000,
        );

        $this->assertTrue($result->truncated);
        $this->assertLessThanOrEqual(4000, mb_strlen($result->text));
        $this->assertStringContainsString('Показаны первые', $result->text);
    }

    public function test_content_exceeding_limit_before_build_is_reduced_to_limit(): void
    {
        $rows = [];

        for ($i = 1; $i <= 50; $i++) {
            $rows[] = ['id' => (string) $i, 'shop' => str_repeat("X{$i}", 80)];
        }

        $result = $this->builder->build(
            intro: 'Объекты продаж — отчёт',
            columns: $this->columns,
            rows: $rows,
            maxTextLength: 4000,
        );

        $this->assertLessThanOrEqual(4000, mb_strlen($result->text));
    }
}
