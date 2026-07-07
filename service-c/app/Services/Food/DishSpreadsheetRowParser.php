<?php

declare(strict_types=1);

namespace App\Services\Food;

use App\DTO\Food\ImportDishRowDto;
use App\Enums\Food\DishVatRate;
use App\Enums\Food\DishWeightUnit;
use App\Exceptions\Food\FoodDomainException;

/**
 * Парсер строки импорта блюд: колонка A — «Название. {вес}г», колонка B — цена.
 */
class DishSpreadsheetRowParser
{
    public function __construct(
        private readonly FoodMoneyFormatter $moneyFormatter,
    ) {}

    /**
     * @throws FoodDomainException
     */
    public function parse(mixed $nameCell, mixed $priceCell): ImportDishRowDto
    {
        $nameRaw = trim((string) $nameCell);

        if ($nameRaw === '') {
            throw new FoodDomainException('Укажите название блюда в колонке A.');
        }

        if (! preg_match('/^(.+?)\.\s*(\d+)\s*г$/u', $nameRaw, $matches)) {
            throw new FoodDomainException('Колонка A должна быть в формате «Название. 300г».');
        }

        $price = $this->parsePrice($priceCell);

        return new ImportDishRowDto(
            name: trim($matches[1]),
            description: null,
            weight: (string) (int) $matches[2],
            weightUnit: DishWeightUnit::Gram,
            price: $this->moneyFormatter->format($price),
            vatRate: DishVatRate::Ten,
            isAvailable: true,
        );
    }

    /**
     * @throws FoodDomainException
     */
    private function parsePrice(mixed $priceCell): float
    {
        $priceRaw = trim((string) $priceCell);

        if ($priceRaw === '') {
            throw new FoodDomainException('Укажите цену в колонке B.');
        }

        $normalized = str_replace([' ', "\xc2\xa0"], '', $priceRaw);
        $normalized = str_replace(',', '.', $normalized);

        if (! is_numeric($normalized)) {
            throw new FoodDomainException('Цена в колонке B должна быть числом.');
        }

        $price = (float) $normalized;

        if ($price < 0) {
            throw new FoodDomainException('Цена не может быть отрицательной.');
        }

        return $price;
    }
}
