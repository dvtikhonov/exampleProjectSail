<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\DTO\Food\OrderDto;
use App\Models\MaxUser;
use App\Services\Food\FoodOrderMaxMessageBuilder;
use Tests\TestCase;

class FoodOrderMaxMessageBuilderTest extends TestCase
{
    private FoodOrderMaxMessageBuilder $builder;

    protected function setUp(): void
    {
        parent::setUp();

        $this->builder = new FoodOrderMaxMessageBuilder;
    }

    public function test_builds_message_with_delivery(): void
    {
        $order = $this->makeOrder(
            id: 42,
            restaurantName: 'Пиццерия',
            itemsTotal: '950.00',
            deliveryApplicable: true,
            deliveryCost: '200.00',
            total: '1150.00',
            deliveryAddress: 'ул. Ленина, 1',
            itemsSnapshot: [
                [
                    'dish_name' => 'Маргарита',
                    'quantity' => 2,
                    'line_total' => '800.00',
                ],
                [
                    'dish_name' => 'Кола',
                    'quantity' => 1,
                    'line_total' => '150.00',
                ],
            ],
        );

        $maxUser = $this->makeMaxUser(
            maxUserId: 1002,
            firstName: 'Иван',
            username: 'ivan',
        );

        $text = $this->builder->build($order, $maxUser);

        $this->assertSame(
            <<<'TEXT'
Новая заявка №42
Ресторан: Пиццерия
Клиент: Иван (@ivan, id 1002)
Адрес: ул. Ленина, 1

• Маргарита × 2 — 800.00 ₽
• Кола × 1 — 150.00 ₽

Статус: ожидает проверки адреса и состава
Сумма блюд: 950.00 ₽
Доставка: 200.00 ₽
Итого: 1150.00 ₽
TEXT,
            $text,
        );
        $this->assertLessThanOrEqual(4000, mb_strlen($text));
    }

    public function test_builds_message_without_delivery_section_when_not_applicable(): void
    {
        $order = $this->makeOrder(
            id: 7,
            restaurantName: 'Кафе',
            itemsTotal: '250.00',
            deliveryApplicable: false,
            deliveryCost: null,
            total: '250.00',
            deliveryAddress: 'ул. Примерная, 1',
            itemsSnapshot: [
                [
                    'dish_name' => 'Суп',
                    'quantity' => 1,
                    'line_total' => '250.00',
                ],
            ],
        );

        $maxUser = $this->makeMaxUser(
            maxUserId: 501,
            firstName: 'Анна',
        );

        $text = $this->builder->build($order, $maxUser);

        $this->assertStringContainsString('Клиент: Анна (id 501)', $text);
        $this->assertStringContainsString('Статус: ожидает проверки адреса и состава', $text);
        $this->assertStringContainsString('Сумма блюд: 250.00 ₽', $text);
        $this->assertStringContainsString('Итого: 250.00 ₽', $text);
        $this->assertStringNotContainsString('Доставка:', $text);
    }

    public function test_truncates_long_item_list_with_remaining_count_suffix(): void
    {
        $itemsSnapshot = [];

        for ($i = 1; $i <= 120; $i++) {
            $itemsSnapshot[] = [
                'dish_name' => sprintf('Блюдо с очень длинным названием номер %03d', $i),
                'quantity' => 2,
                'line_total' => '999.99',
            ];
        }

        $order = $this->makeOrder(
            id: 99,
            restaurantName: 'Ресторан с длинным названием для проверки обрезки',
            itemsTotal: '119998.80',
            deliveryApplicable: true,
            deliveryCost: '300.00',
            total: '120298.80',
            deliveryAddress: 'ул. Очень длинный адрес доставки, дом 123, корпус 4, квартира 567',
            itemsSnapshot: $itemsSnapshot,
        );

        $maxUser = $this->makeMaxUser(
            maxUserId: 77_777,
            firstName: 'Покупатель',
            lastName: 'С длинным именем',
            username: 'long_username_for_test',
        );

        $text = $this->builder->build($order, $maxUser, maxTextLength: 4000);

        $this->assertLessThanOrEqual(4000, mb_strlen($text));
        $this->assertStringContainsString('…и ещё ', $text);
        $this->assertStringContainsString(' позиций', $text);
        $this->assertStringContainsString('Новая заявка №99', $text);
        $this->assertStringContainsString('Итого: 120298.80 ₽', $text);
        $this->assertLessThan(120, substr_count($text, '• '));
    }

    public function test_formats_client_without_username_using_name_and_id(): void
    {
        $order = $this->makeOrder(
            id: 1,
            restaurantName: 'Test',
            itemsTotal: '100.00',
            deliveryApplicable: false,
            deliveryCost: null,
            total: '100.00',
            deliveryAddress: 'Адрес',
            itemsSnapshot: [
                [
                    'dish_name' => 'Блюдо',
                    'quantity' => 1,
                    'line_total' => '100.00',
                ],
            ],
        );

        $maxUser = $this->makeMaxUser(
            maxUserId: 42,
            firstName: 'Пётр',
            lastName: 'Петров',
        );

        $text = $this->builder->build($order, $maxUser);

        $this->assertStringContainsString('Клиент: Пётр Петров (id 42)', $text);
        $this->assertStringNotContainsString('@', $text);
    }

    /**
     * @param  list<array<string, int|string>>  $itemsSnapshot
     */
    private function makeOrder(
        int $id,
        string $restaurantName,
        string $itemsTotal,
        bool $deliveryApplicable,
        ?string $deliveryCost,
        string $total,
        ?string $deliveryAddress,
        array $itemsSnapshot,
    ): OrderDto {
        return new OrderDto(
            id: $id,
            status: 'submitted',
            restaurantId: 1,
            restaurantName: $restaurantName,
            itemsTotal: $itemsTotal,
            deliveryApplicable: $deliveryApplicable,
            deliveryCost: $deliveryCost,
            total: $total,
            deliveryAddress: $deliveryAddress,
            itemsSnapshot: $itemsSnapshot,
            createdAt: '2026-06-22T12:00:00+00:00',
        );
    }

    private function makeMaxUser(
        int $maxUserId,
        ?string $firstName = null,
        ?string $lastName = null,
        ?string $username = null,
    ): MaxUser {
        return new MaxUser([
            'max_user_id' => $maxUserId,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'username' => $username,
        ]);
    }
}
