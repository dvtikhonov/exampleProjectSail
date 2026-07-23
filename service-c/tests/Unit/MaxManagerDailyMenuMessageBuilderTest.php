<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\DTO\Food\DailyMenuDishPartDto;
use App\DTO\Food\DailyMenuLineDto;
use App\Enums\Food\DailyMenuLineType;
use App\Services\Food\MaxManagerDailyMenuMessageBuilder;
use Carbon\CarbonImmutable;
use Tests\TestCase;

class MaxManagerDailyMenuMessageBuilderTest extends TestCase
{
    /** Builder собирает два текста: без доставки и с доставкой. */
    public function test_build_formats_single_and_combo_lines_with_and_without_delivery(): void
    {
        $builder = new MaxManagerDailyMenuMessageBuilder;
        $menuDate = CarbonImmutable::parse('2026-07-22', 'Europe/Moscow');

        $messages = $builder->build($menuDate, [
            new DailyMenuLineDto(
                type: DailyMenuLineType::Single,
                parts: [
                    new DailyMenuDishPartDto(
                        name: 'Салат "Цезарь"',
                        description: 'курица, пекинская капуста, помидор, сухарики, сыр, майонез',
                        weightLabel: '110г',
                        price: 97.0,
                    ),
                ],
            ),
            new DailyMenuLineDto(
                type: DailyMenuLineType::Single,
                parts: [
                    new DailyMenuDishPartDto(
                        name: 'Запеканка куриная с баклажанами',
                        description: null,
                        weightLabel: '200г',
                        price: 210.0,
                    ),
                ],
            ),
            new DailyMenuLineDto(
                type: DailyMenuLineType::Combo,
                parts: [
                    new DailyMenuDishPartDto(
                        name: 'Филе минтая, запеченное с грибами',
                        description: null,
                        weightLabel: '130г',
                        price: 120.0,
                    ),
                    new DailyMenuDishPartDto(
                        name: 'Макароны',
                        description: null,
                        weightLabel: '150г',
                        price: 82.0,
                    ),
                ],
            ),
        ]);

        $expectedBody = implode("\n", [
            'Добрый день!',
            'Меню на 22.07.26:',
            '1. Салат "Цезарь" (курица, пекинская капуста, помидор, сухарики, сыр, майонез), 110г – 97р',
            '2. Запеканка куриная с баклажанами, 200г – 210р',
            '3. Филе минтая, запеченное с грибами / Макароны, 130г / 150г – 202р',
        ]);

        $orderRules = 'С ПН по ЧТ принимаем заказы до 10.00 и привозим еду на следующий день утром. Заказы, принятые после 10 часов, будут обслуживаться по факту наличия готовой продукции. В пятницу принимаем заказы на понедельник.';
        $delivery = 'Стоимость доставки 100 руб., при заказе на 1 тысячу рублей и больше – доставка бесплатно.';

        $this->assertSame($expectedBody."\n".$orderRules, $messages->withoutDelivery);
        $this->assertSame($expectedBody."\n".$orderRules.' '.$delivery, $messages->withDelivery);
    }
}
