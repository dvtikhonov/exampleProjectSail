<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\Food\OrderRejectionScope;
use App\Models\FoodOrder;
use App\Models\Restaurant;
use App\Services\Food\FoodOrderMaxMessageBuilder;
use App\Services\Food\LaravelFoodOrderCustomerNotifier;
use App\Support\MaxOpenAppTargetResolver;
use App\Support\OrderSnapshotComboResolver;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Shared\MaxMessenger\Contracts\MaxMessengerClientInterface;
use Shared\MaxMessenger\DTO\MaxInlineKeyboardMessageDto;
use Shared\MaxMessenger\DTO\MaxMessageDto;
use Shared\MaxMessenger\Exceptions\MaxMessengerRequestException;
use Tests\Support\MessMaxLogTestHelper;
use Tests\TestCase;

class FoodOrderCustomerNotifierTest extends TestCase
{
    private FoodOrderMaxMessageBuilder $messageBuilder;

    /** Подготовка окружения перед тестом. */
    protected function setUp(): void
    {
        parent::setUp();

        $this->messageBuilder = new FoodOrderMaxMessageBuilder(new OrderSnapshotComboResolver);
    }

    /** Собирает сообщение клиенту об отправке заказа. */
    public function test_build_customer_submitted_message(): void
    {
        $order = $this->makeOrder(id: 11);

        $text = $this->messageBuilder->buildCustomerSubmitted($order);

        $this->assertSame(
            'Заказ №11 принят на рассмотрение. В чате заказа можете сделать уточнения к заказу',
            $text,
        );
    }

    /** Собирает сообщение клиенту о подтверждении заказа. */
    public function test_build_customer_confirmed_message(): void
    {
        $order = $this->makeOrder(id: 15);

        $text = $this->messageBuilder->buildCustomerConfirmed($order);

        $this->assertSame('Заявка №15 принята к исполнению', $text);
    }

    /** Собирает сообщение клиенту об отклонении по адресу. */
    public function test_build_customer_rejected_message_for_address(): void
    {
        $order = $this->makeOrder(
            id: 8,
            addressRejectionComment: 'Адрес вне зоны доставки',
        );

        $text = $this->messageBuilder->buildCustomerRejected($order, OrderRejectionScope::Address);

        $this->assertSame(
            <<<'TEXT'
Заявка №8 отклонена
Проверка: адрес доставки
Причина: Адрес вне зоны доставки
TEXT,
            $text,
        );
    }

    /** Собирает сообщение клиенту об отклонении по составу. */
    public function test_build_customer_rejected_message_for_composition(): void
    {
        $order = $this->makeOrder(
            id: 21,
            compositionRejectionComment: 'Блюдо временно недоступно',
        );

        $text = $this->messageBuilder->buildCustomerRejected($order, OrderRejectionScope::Composition);

        $this->assertSame(
            <<<'TEXT'
Заявка №21 отклонена
Проверка: состав заказа
Причина: Блюдо временно недоступно
TEXT,
            $text,
        );
    }

    /** notifySubmitted отправляет сообщение с кнопкой открытия заказа. */
    public function test_notify_submitted_sends_message_with_open_app_button(): void
    {
        Config::set('max.ui_stand.mini_app_url', 'https://example.test/max-app');
        Config::set('max.bot_user_id', 421816864057);

        $sentMessage = null;
        $client = $this->createMock(MaxMessengerClientInterface::class);
        $client
            ->expects($this->once())
            ->method('sendInlineKeyboardMessage')
            ->willReturnCallback(function (MaxInlineKeyboardMessageDto $message) use (&$sentMessage): void {
                $sentMessage = $message;
            });
        $client->expects($this->never())->method('sendMessage');

        $notifier = $this->makeNotifier($client);

        $order = $this->makeOrder(id: 33, maxUserId: 2001);

        $notifier->notifySubmitted($order);

        $this->assertNotNull($sentMessage);
        $this->assertSame(2001, $sentMessage->userId);
        $this->assertNull($sentMessage->chatId);
        $this->assertSame(
            'Заказ №33 принят на рассмотрение. В чате заказа можете сделать уточнения к заказу',
            $sentMessage->text,
        );
        $this->assertSame('Открыть заказ №33', $sentMessage->buttonRows[0][0]->text);
        $this->assertSame('open_app', $sentMessage->buttonRows[0][0]->type);
        $this->assertSame('https://example.test/max-app', $sentMessage->buttonRows[0][0]->webApp);
        $this->assertSame('order_33_chat', $sentMessage->buttonRows[0][0]->payload);
        $this->assertSame(421816864057, $sentMessage->buttonRows[0][0]->contactId);
    }

    /** notifySubmitted без mini-app URL отправляет обычное сообщение. */
    public function test_notify_submitted_falls_back_to_plain_message_without_open_app_target(): void
    {
        $this->disableOpenAppTarget();

        $sentMessage = null;
        $client = $this->createMock(MaxMessengerClientInterface::class);
        $client
            ->expects($this->once())
            ->method('sendMessage')
            ->willReturnCallback(function (MaxMessageDto $message) use (&$sentMessage): void {
                $sentMessage = $message;
            });
        $client->expects($this->never())->method('sendInlineKeyboardMessage');

        $notifier = $this->makeNotifier($client);

        $order = $this->makeOrder(id: 33, maxUserId: 2001);

        $notifier->notifySubmitted($order);

        $this->assertNotNull($sentMessage);
        $this->assertSame(2001, $sentMessage->userId);
        $this->assertSame(
            'Заказ №33 принят на рассмотрение. В чате заказа можете сделать уточнения к заказу',
            $sentMessage->text,
        );
    }

    /** Собирает сообщение клиенту об изменении состава. */
    public function test_build_customer_composition_changed_message(): void
    {
        $order = $this->makeOrder(
            id: 55,
            restaurantName: 'Пиццерия',
            deliveryAddress: 'ул. Ленина, 1',
            itemsTotal: '800.00',
            deliveryCost: '200.00',
            total: '1000.00',
            itemsSnapshot: [
                [
                    'dish_id' => 1,
                    'dish_name' => 'Маргарита',
                    'quantity' => 2,
                    'line_total' => '800.00',
                ],
            ],
        );

        $text = $this->messageBuilder->buildCustomerCompositionChanged($order);

        $this->assertStringContainsString('Заказ изменен по вашему согласованию', $text);
        $this->assertStringContainsString('Заказ №55', $text);
        $this->assertStringContainsString('Ресторан: Пиццерия', $text);
        $this->assertStringContainsString('• Маргарита × 2 — 800.00 ₽', $text);
        $this->assertStringContainsString('Итого: 1000.00 ₽', $text);
    }

    /** notifyCompositionChanged отправляет сообщение с кнопкой открытия заказа. */
    public function test_notify_composition_changed_sends_message_with_open_app_button(): void
    {
        Config::set('max.ui_stand.mini_app_url', 'https://example.test/max-app');
        Config::set('max.bot_user_id', 421816864057);

        $sentMessage = null;
        $client = $this->createMock(MaxMessengerClientInterface::class);
        $client
            ->expects($this->once())
            ->method('sendInlineKeyboardMessage')
            ->willReturnCallback(function (MaxInlineKeyboardMessageDto $message) use (&$sentMessage): void {
                $sentMessage = $message;
            });
        $client->expects($this->never())->method('sendMessage');

        $notifier = $this->makeNotifier($client);

        $order = $this->makeOrder(
            id: 55,
            maxUserId: 2001,
            restaurantName: 'Пиццерия',
            itemsTotal: '500.00',
            total: '500.00',
            itemsSnapshot: [
                [
                    'dish_id' => 1,
                    'dish_name' => 'Суп',
                    'quantity' => 1,
                    'line_total' => '500.00',
                ],
            ],
        );

        $notifier->notifyCompositionChanged($order);

        $this->assertNotNull($sentMessage);
        $this->assertSame(2001, $sentMessage->userId);
        $this->assertStringContainsString('Заказ изменен по вашему согласованию', $sentMessage->text);
        $this->assertStringContainsString('• Суп × 1 — 500.00 ₽', $sentMessage->text);
        $this->assertSame('Открыть заказ №55', $sentMessage->buttonRows[0][0]->text);
        $this->assertSame('open_app', $sentMessage->buttonRows[0][0]->type);
        $this->assertSame('order_55_chat', $sentMessage->buttonRows[0][0]->payload);
    }

    /** notifyConfirmed отправляет сообщение клиенту заказа. */
    public function test_notify_confirmed_sends_message_to_order_customer(): void
    {
        $sentMessage = null;
        $client = $this->createMock(MaxMessengerClientInterface::class);
        $client
            ->expects($this->once())
            ->method('sendMessage')
            ->willReturnCallback(function (MaxMessageDto $message) use (&$sentMessage): void {
                $sentMessage = $message;
            });

        $notifier = $this->makeNotifier($client);

        $order = $this->makeOrder(id: 42, maxUserId: 1002);

        $notifier->notifyConfirmed($order);

        $this->assertNotNull($sentMessage);
        $this->assertSame(1002, $sentMessage->userId);
        $this->assertNull($sentMessage->chatId);
        $this->assertSame('Заявка №42 принята к исполнению', $sentMessage->text);
    }

    /** notifyRejected отправляет сообщение со scope и комментарием. */
    public function test_notify_rejected_sends_message_with_scope_and_comment(): void
    {
        $sentMessage = null;
        $client = $this->createMock(MaxMessengerClientInterface::class);
        $client
            ->expects($this->once())
            ->method('sendMessage')
            ->willReturnCallback(function (MaxMessageDto $message) use (&$sentMessage): void {
                $sentMessage = $message;
            });

        $notifier = $this->makeNotifier($client);

        $order = $this->makeOrder(
            id: 7,
            maxUserId: 501,
            compositionRejectionComment: 'Нет ингредиентов',
        );

        $notifier->notifyRejected($order, OrderRejectionScope::Composition);

        $this->assertNotNull($sentMessage);
        $this->assertSame(501, $sentMessage->userId);
        $this->assertStringContainsString('Заявка №7 отклонена', $sentMessage->text);
        $this->assertStringContainsString('Проверка: состав заказа', $sentMessage->text);
        $this->assertStringContainsString('Причина: Нет ингредиентов', $sentMessage->text);
    }

    /** Логирует предупреждение, когда отправка не удалась. */
    public function test_notify_logs_warning_when_send_fails(): void
    {
        $captured = [];

        Log::channel('messMax')->listen(function (MessageLogged $event) use (&$captured): void {
            $captured[] = $event;
        });

        $client = $this->createMock(MaxMessengerClientInterface::class);
        $client
            ->expects($this->once())
            ->method('sendMessage')
            ->willThrowException(new MaxMessengerRequestException('User blocked bot'));

        $notifier = $this->makeNotifier($client);

        $order = $this->makeOrder(id: 3, maxUserId: 99);

        $notifier->notifyConfirmed($order);

        $log = MessMaxLogTestHelper::assertSingleMessage($captured, 'MAX customer order notification send failed');
        $this->assertSame('warning', $log->level);
        $this->assertSame(3, $log->context['order_id']);
        $this->assertSame(99, $log->context['max_user_id']);
        $this->assertSame('User blocked bot', $log->context['error']);
    }

    /** Создаёт notifier с подставным MAX-клиентом. */
    private function makeNotifier(MaxMessengerClientInterface $client): LaravelFoodOrderCustomerNotifier
    {
        return new LaravelFoodOrderCustomerNotifier(
            client: $client,
            messageBuilder: $this->messageBuilder,
            openAppTargetResolver: $this->app->make(MaxOpenAppTargetResolver::class),
        );
    }

    /** Отключает цель open-app для теста. */
    private function disableOpenAppTarget(): void
    {
        Config::set('max.ui_stand.mini_app_url', '');
        Config::set('max.public_app_url', '');
        Config::set('max.webhook.url', '');
        Config::set('max.bot_username', '');
    }

    /** Создаёт тестовый заказ. */
    private function makeOrder(
        int $id,
        int $maxUserId = 1000,
        ?string $addressRejectionComment = null,
        ?string $compositionRejectionComment = null,
        ?string $restaurantName = null,
        ?string $deliveryAddress = null,
        ?string $itemsTotal = null,
        ?string $deliveryCost = null,
        ?string $total = null,
        ?array $itemsSnapshot = null,
    ): FoodOrder {
        $order = new FoodOrder([
            'max_user_id' => $maxUserId,
            'address_rejection_comment' => $addressRejectionComment,
            'composition_rejection_comment' => $compositionRejectionComment,
            'delivery_address' => $deliveryAddress,
            'items_total' => $itemsTotal,
            'delivery_cost' => $deliveryCost,
            'total' => $total,
            'items_snapshot' => $itemsSnapshot,
        ]);
        $order->id = $id;

        if ($restaurantName !== null) {
            $order->setRelation('restaurant', new Restaurant(['name' => $restaurantName]));
        }

        return $order;
    }
}
