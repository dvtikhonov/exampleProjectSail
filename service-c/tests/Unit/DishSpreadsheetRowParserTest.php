<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\Food\DishVatRate;
use App\Enums\Food\DishWeightUnit;
use App\Services\Food\DishSpreadsheetRowParser;
use Tests\TestCase;

class DishSpreadsheetRowParserTest extends TestCase
{
    private DishSpreadsheetRowParser $parser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->parser = new DishSpreadsheetRowParser;
    }

    public function test_parses_name_weight_and_price_from_valid_row(): void
    {
        $result = $this->parser->parseRows([
            ['Блюдо', 'Цена'],
            ['Борщ.350г', '150'],
        ]);

        $this->assertSame([], $result['errors']);
        $this->assertCount(1, $result['rows']);

        $row = $result['rows'][0];
        $this->assertSame('Борщ', $row->name);
        $this->assertSame('350', $row->weight);
        $this->assertSame(DishWeightUnit::Gram, $row->weightUnit);
        $this->assertSame('150.00', $row->price);
        $this->assertSame(DishVatRate::Exempt, $row->vatRate);
        $this->assertTrue($row->isAvailable);
        $this->assertNull($row->description);
    }

    public function test_parses_decimal_price_with_comma_separator(): void
    {
        $result = $this->parser->parseRows([
            ['Блюдо', 'Цена'],
            ['Пицца Маргарита.450г', '520,50'],
        ]);

        $this->assertSame([], $result['errors']);
        $this->assertCount(1, $result['rows']);
        $this->assertSame('Пицца Маргарита', $result['rows'][0]->name);
        $this->assertSame('450', $result['rows'][0]->weight);
        $this->assertSame('520.50', $result['rows'][0]->price);
    }

    public function test_parses_name_with_space_before_weight_from_menu_spreadsheet(): void
    {
        $result = $this->parser->parseRows([
            ['Горячее (добавляем новые, формат = Блюдо. 100г)', 'р.'],
            ['Свинина, запеченная с сыром. 130г', '150'],
            ['Филе горбуши, запечённое с грибами. 130 г', '170'],
            [
                'Минтай по-деревенски (минтай, картофель, лук, сметана, сыр, масло растительное). 200г',
                '190',
            ],
        ]);

        $this->assertSame([], $result['errors']);
        $this->assertCount(3, $result['rows']);
        $this->assertSame('Свинина, запеченная с сыром', $result['rows'][0]->name);
        $this->assertSame('130', $result['rows'][0]->weight);
        $this->assertSame('150.00', $result['rows'][0]->price);
        $this->assertSame('Филе горбуши, запечённое с грибами', $result['rows'][1]->name);
        $this->assertSame('130', $result['rows'][1]->weight);
        $this->assertSame('Минтай по-деревенски (минтай, картофель, лук, сметана, сыр, масло растительное)', $result['rows'][2]->name);
        $this->assertSame('200', $result['rows'][2]->weight);
    }

    public function test_invalid_column_a_produces_error_with_row_number(): void
    {
        $result = $this->parser->parseRows([
            ['Блюдо', 'Цена'],
            ['Борщ.350г', '150'],
            ['Неверный формат', '200'],
        ]);

        $this->assertCount(1, $result['rows']);
        $this->assertSame('Борщ', $result['rows'][0]->name);
        $this->assertCount(1, $result['errors']);
        $this->assertSame(3, $result['errors'][0]['row']);
        $this->assertStringContainsString('колонки A', $result['errors'][0]['message']);
    }

    public function test_empty_row_is_skipped(): void
    {
        $result = $this->parser->parseRows([
            ['Блюдо', 'Цена'],
            ['Борщ.350г', '150'],
            ['', ''],
            ['Салат Цезарь.200г', '280'],
        ]);

        $this->assertSame([], $result['errors']);
        $this->assertCount(2, $result['rows']);
        $this->assertSame('Борщ', $result['rows'][0]->name);
        $this->assertSame('Салат Цезарь', $result['rows'][1]->name);
    }

    public function test_invalid_column_b_produces_error_with_row_number(): void
    {
        $result = $this->parser->parseRows([
            ['Блюдо', 'Цена'],
            ['Борщ.350г', 'не цена'],
        ]);

        $this->assertSame([], $result['rows']);
        $this->assertCount(1, $result['errors']);
        $this->assertSame(2, $result['errors'][0]['row']);
        $this->assertStringContainsString('колонки B', $result['errors'][0]['message']);
    }
}
