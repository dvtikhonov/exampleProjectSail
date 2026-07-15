<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\Food\DishVatRate;
use App\Enums\Food\DishWeightUnit;
use App\Exceptions\Food\FoodDomainException;
use App\Services\Food\DishSpreadsheetRowParser;
use App\Services\Food\FoodMoneyFormatter;
use Tests\TestCase;

class DishSpreadsheetRowParserTest extends TestCase
{
    private DishSpreadsheetRowParser $parser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->parser = new DishSpreadsheetRowParser(new FoodMoneyFormatter);
    }

    public function test_parses_name_weight_and_price_from_valid_row(): void
    {
        $row = $this->parser->parse('Борщ.350г', '150');

        $this->assertSame('Борщ', $row->name);
        $this->assertSame('350', $row->weight);
        $this->assertSame(DishWeightUnit::Gram, $row->weightUnit);
        $this->assertSame('150.00', $row->price);
        $this->assertSame(DishVatRate::Exempt, $row->vatRate);
        $this->assertFalse($row->isAvailable);
        $this->assertNull($row->description);
    }

    public function test_parses_decimal_price_with_comma_separator(): void
    {
        $row = $this->parser->parse('Пицца Маргарита.450г', '520,50');

        $this->assertSame('Пицца Маргарита', $row->name);
        $this->assertSame('450', $row->weight);
        $this->assertSame('520.50', $row->price);
    }

    public function test_parses_name_with_space_before_weight_from_menu_spreadsheet(): void
    {
        $row1 = $this->parser->parse('Свинина, запеченная с сыром. 130г', '150');
        $row2 = $this->parser->parse('Филе горбуши, запечённое с грибами. 130 г', '170');
        $row3 = $this->parser->parse(
            'Минтай по-деревенски (минтай, картофель, лук, сметана, сыр, масло растительное). 200г',
            '190',
        );

        $this->assertSame('Свинина, запеченная с сыром', $row1->name);
        $this->assertSame('130', $row1->weight);
        $this->assertSame('150.00', $row1->price);
        $this->assertSame('Филе горбуши, запечённое с грибами', $row2->name);
        $this->assertSame('130', $row2->weight);
        $this->assertSame(
            'Минтай по-деревенски (минтай, картофель, лук, сметана, сыр, масло растительное)',
            $row3->name,
        );
        $this->assertSame('200', $row3->weight);
    }

    public function test_invalid_column_a_throws_domain_exception(): void
    {
        $this->expectException(FoodDomainException::class);
        $this->expectExceptionMessage('Колонка A должна быть в формате');

        $this->parser->parse('Неверный формат', '200');
    }

    public function test_empty_name_cell_throws_domain_exception(): void
    {
        $this->expectException(FoodDomainException::class);
        $this->expectExceptionMessage('колонке A');

        $this->parser->parse('', '');
    }

    public function test_invalid_column_b_throws_domain_exception(): void
    {
        $this->expectException(FoodDomainException::class);
        $this->expectExceptionMessage('колонке B');

        $this->parser->parse('Борщ.350г', 'не цена');
    }
}
