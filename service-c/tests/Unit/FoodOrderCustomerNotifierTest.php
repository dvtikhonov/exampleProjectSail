<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Contracts\Food\Review\OrderCustomerNotifyRecipientResolverInterface;
use App\Contracts\Max\MaxMessengerNotificationSenderInterface;
use App\Contracts\Max\MaxUiStandRecipientResolverInterface;
use App\DTO\Food\Order\FoodOrderRecord;
use App\Enums\Food\Order\OrderStatus;
use App\Enums\Food\Review\OrderRejectionScope;
use App\Enums\Food\Review\OrderReviewStatus;
use App\Infrastructure\Laravel\LaravelFoodOrderCustomerNotifier;
use App\Services\Max\Food\FoodOrderMaxMessageBuilder;
use App\Services\Max\MaxMessengerNotificationSender;
use App\Support\Food\Composition\OrderSnapshotComboResolver;
use App\Support\Max\MaxOpenAppButtonFactory;
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

    /** Собирает детальное уведомление оформившему ручной заказ. */
    public function test_build_manual_order_creator_confirmed_message(): void
    {
        $order = $this->makeOrder(
            id: 21,
            maxUserId: 1002,
            isManual: true,
            createdByMaxUserId: 9001,
            createdAt: '2026-07-21 10:00:00',
            customerFirstName: 'Иван',
            customerLastName: 'Петров',
            itemsSnapshot: [
                [
                    'dish_id' => 1,
                    'dish_name' => 'Салат "Гнездо глухаря"',
                    'description' => 'курица, картофель пай, яйцо, лук жареный, майонез',
                    'weight' => '110',
                    'weight_unit' => 'g',
                    'unit_price' => '97.00',
                    'quantity' => 2,
                    'line_total' => '194.00',
                ],
                [
                    'dish_id' => 2,
                    'dish_name' => 'Терпуг запеченный с овощами',
                    'weight' => '130',
                    'weight_unit' => 'g',
                    'unit_price' => '100.00',
                    'quantity' => 1,
                    'line_total' => '100.00',
                    'combo_ref' => 'combo-1',
                    'combo_partner_dish_ids' => [3],
                ],
                [
                    'dish_id' => 3,
                    'dish_name' => 'Гречка',
                    'weight' => '150',
                    'weight_unit' => 'g',
                    'unit_price' => '60.00',
                    'quantity' => 1,
                    'line_total' => '60.00',
                    'combo_ref' => 'combo-1',
                    'combo_partner_dish_ids' => [2],
                ],
                [
                    'dish_id' => 4,
                    'dish_name' => 'Поджарка из курицы',
                    'weight' => '100',
                    'weight_unit' => 'g',
                    'unit_price' => '135.00',
                    'quantity' => 3,
                    'line_total' => '405.00',
                    'combo_ref' => 'combo-2',
                    'combo_partner_dish_ids' => [5],
                ],
                [
                    'dish_id' => 5,
                    'dish_name' => 'Гречка',
                    'weight' => '150',
                    'weight_unit' => 'g',
                    'unit_price' => '50.00',
                    'quantity' => 3,
                    'line_total' => '150.00',
                    'combo_ref' => 'combo-2',
                    'combo_partner_dish_ids' => [4],
                ],
            ],
        );

        $text = $this->messageBuilder->buildManualOrderCreatorConfirmed($order);

        $this->assertSame(
            <<<'TEXT'
Заказ на 21.07. от Иван Петров:
1. Салат "Гнездо глухаря" (курица, картофель пай, яйцо, лук жареный, майонез), 110г – 97р - 2шт.
2. Терпуг запеченный с овощами / Гречка, 130г / 150г – 160р - 1шт.
3. Поджарка из курицы / Гречка, 100г / 150г – 185р - 3шт.
TEXT,
            $text,
        );
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

    /** Для ручного заказа уведомление уходит активным max_manager, не клиенту. */
    public function test_notify_confirmed_manual_order_sends_to_managers_not_customer(): void
    {
        $sentUserIds = [];
        $client = $this->createMock(MaxMessengerClientInterface::class);
        $client
            ->expects($this->exactly(2))
            ->method('sendMessage')
            ->willReturnCallback(function (MaxMessageDto $message) use (&$sentUserIds): void {
                $sentUserIds[] = $message->userId;
            });

        $recipientResolver = $this->createMock(OrderCustomerNotifyRecipientResolverInterface::class);
        $recipientResolver
            ->expects($this->once())
            ->method('resolveMaxUserIds')
            ->willReturn([9001, 9002]);

        $notifier = $this->makeNotifier($client, $recipientResolver);

        $order = $this->makeOrder(id: 42, maxUserId: 1002, isManual: true);

        $notifier->notifyConfirmed($order);

        $this->assertSame([9001, 9002], $sentUserIds);
        $this->assertNotContains(1002, $sentUserIds);
    }

    /** После confirm ручного заказа оформившему уходит детальный состав. */
    public function test_notify_confirmed_manual_order_sends_detailed_message_to_creator(): void
    {
        $sentMessages = [];
        $client = $this->createMock(MaxMessengerClientInterface::class);
        $client
            ->expects($this->exactly(2))
            ->method('sendMessage')
            ->willReturnCallback(function (MaxMessageDto $message) use (&$sentMessages): void {
                $sentMessages[] = $message;
            });

        $recipientResolver = $this->createMock(OrderCustomerNotifyRecipientResolverInterface::class);
        $recipientResolver
            ->expects($this->once())
            ->method('resolveMaxUserIds')
            ->willReturn([9001]);

        $notifier = $this->makeNotifier($client, $recipientResolver);

        $order = $this->makeOrder(
            id: 42,
            maxUserId: 1002,
            isManual: true,
            createdByMaxUserId: 9001,
            createdAt: '2026-07-21 12:00:00',
            customerFirstName: 'Анна',
            customerLastName: 'Сидорова',
            itemsSnapshot: [
                [
                    'dish_id' => 1,
                    'dish_name' => 'Суп',
                    'description' => 'куриный бульон',
                    'weight' => '250',
                    'weight_unit' => 'g',
                    'unit_price' => '120.00',
                    'quantity' => 1,
                    'line_total' => '120.00',
                ],
            ],
        );

        $notifier->notifyConfirmed($order);

        $this->assertCount(2, $sentMessages);
        $this->assertSame(9001, $sentMessages[0]->userId);
        $this->assertSame('Заявка №42 принята к исполнению', $sentMessages[0]->text);
        $this->assertSame(9001, $sentMessages[1]->userId);
        $this->assertSame(
            <<<'TEXT'
Заказ на 21.07. от Анна Сидорова:
1. Суп (куриный бульон), 250г – 120р - 1шт.
TEXT,
            $sentMessages[1]->text,
        );
    }

    /** Если DM оформившему недоступен — «Заказ на» уходит в UI Stand chat. */
    public function test_notify_confirmed_manual_order_falls_back_to_ui_stand_when_creator_dm_fails(): void
    {
        $sentMessages = [];
        $client = $this->createMock(MaxMessengerClientInterface::class);
        $client
            ->expects($this->exactly(3))
            ->method('sendMessage')
            ->willReturnCallback(function (MaxMessageDto $message) use (&$sentMessages): void {
                $sentMessages[] = $message;

                if ($message->userId === 1003) {
                    throw new MaxMessengerRequestException('Получатель MAX не найден. Проверьте chat_id и user_id в настройках.');
                }
            });

        $recipientResolver = $this->createMock(OrderCustomerNotifyRecipientResolverInterface::class);
        $recipientResolver
            ->expects($this->once())
            ->method('resolveMaxUserIds')
            ->willReturn([1003]);

        $uiStandResolver = $this->createMock(MaxUiStandRecipientResolverInterface::class);
        $uiStandResolver->method('chatIds')->willReturn([-75495934087316]);
        $uiStandResolver->method('userIds')->willReturn([]);

        $notifier = $this->makeNotifier($client, $recipientResolver, $uiStandResolver);

        $order = $this->makeOrder(
            id: 42,
            maxUserId: 1002,
            isManual: true,
            createdByMaxUserId: 1003,
            createdAt: '2026-07-21 12:00:00',
            customerFirstName: 'Demo',
            customerLastName: 'VIP',
            itemsSnapshot: [
                [
                    'dish_id' => 1,
                    'dish_name' => 'Суп',
                    'weight' => '250',
                    'weight_unit' => 'g',
                    'unit_price' => '120.00',
                    'quantity' => 1,
                    'line_total' => '120.00',
                ],
            ],
        );

        $notifier->notifyConfirmed($order);

        $this->assertCount(3, $sentMessages);
        $this->assertSame(1003, $sentMessages[0]->userId);
        $this->assertSame('Заявка №42 принята к исполнению', $sentMessages[0]->text);
        $this->assertSame(1003, $sentMessages[1]->userId);
        $this->assertStringStartsWith('Заказ на 21.07.', $sentMessages[1]->text);
        $this->assertSame(-75495934087316, $sentMessages[2]->chatId);
        $this->assertNull($sentMessages[2]->userId);
        $this->assertSame($sentMessages[1]->text, $sentMessages[2]->text);
    }

    /** Публичный notify оформившему не рассылает всем max_manager — только created_by_max_user_id. */
    public function test_notify_manual_order_creator_confirmed_sends_only_to_creator(): void
    {
        $sentMessages = [];
        $client = $this->createMock(MaxMessengerClientInterface::class);
        $client
            ->expects($this->once())
            ->method('sendMessage')
            ->willReturnCallback(function (MaxMessageDto $message) use (&$sentMessages): void {
                $sentMessages[] = $message;
            });

        $recipientResolver = $this->createMock(OrderCustomerNotifyRecipientResolverInterface::class);
        $recipientResolver->expects($this->never())->method('resolveMaxUserIds');

        $notifier = $this->makeNotifier($client, $recipientResolver);

        $order = $this->makeOrder(
            id: 42,
            maxUserId: 1002,
            isManual: true,
            createdByMaxUserId: 9001,
            createdAt: '2026-07-21 12:00:00',
            customerFirstName: 'Анна',
            customerLastName: 'Сидорова',
            itemsSnapshot: [
                [
                    'dish_id' => 1,
                    'dish_name' => 'Суп',
                    'description' => 'куриный бульон',
                    'weight' => '250',
                    'weight_unit' => 'g',
                    'unit_price' => '120.00',
                    'quantity' => 1,
                    'line_total' => '120.00',
                ],
            ],
        );

        $notifier->notifyManualOrderCreatorConfirmed($order);

        $this->assertCount(1, $sentMessages);
        $this->assertSame(9001, $sentMessages[0]->userId);
        $this->assertSame(
            <<<'TEXT'
Заказ на 21.07. от Анна Сидорова:
1. Суп (куриный бульон), 250г – 120р - 1шт.
TEXT,
            $sentMessages[0]->text,
        );
    }

    /** Если DM оформившему недоступен — публичный notify уходит в UI Stand. */
    public function test_notify_manual_order_creator_confirmed_falls_back_to_ui_stand(): void
    {
        $sentMessages = [];
        $client = $this->createMock(MaxMessengerClientInterface::class);
        $client
            ->expects($this->exactly(2))
            ->method('sendMessage')
            ->willReturnCallback(function (MaxMessageDto $message) use (&$sentMessages): void {
                $sentMessages[] = $message;

                if ($message->userId === 1003) {
                    throw new MaxMessengerRequestException('Получатель MAX не найден. Проверьте chat_id и user_id в настройках.');
                }
            });

        $recipientResolver = $this->createMock(OrderCustomerNotifyRecipientResolverInterface::class);
        $recipientResolver->expects($this->never())->method('resolveMaxUserIds');

        $uiStandResolver = $this->createMock(MaxUiStandRecipientResolverInterface::class);
        $uiStandResolver->method('chatIds')->willReturn([-75495934087316]);
        $uiStandResolver->method('userIds')->willReturn([]);

        $notifier = $this->makeNotifier($client, $recipientResolver, $uiStandResolver);

        $order = $this->makeOrder(
            id: 42,
            maxUserId: 1002,
            isManual: true,
            createdByMaxUserId: 1003,
            createdAt: '2026-07-21 12:00:00',
            customerFirstName: 'Demo',
            customerLastName: 'VIP',
            itemsSnapshot: [
                [
                    'dish_id' => 1,
                    'dish_name' => 'Суп',
                    'weight' => '250',
                    'weight_unit' => 'g',
                    'unit_price' => '120.00',
                    'quantity' => 1,
                    'line_total' => '120.00',
                ],
            ],
        );

        $notifier->notifyManualOrderCreatorConfirmed($order);

        $this->assertCount(2, $sentMessages);
        $this->assertSame(1003, $sentMessages[0]->userId);
        $this->assertStringStartsWith('Заказ на 21.07.', $sentMessages[0]->text);
        $this->assertSame(-75495934087316, $sentMessages[1]->chatId);
        $this->assertNull($sentMessages[1]->userId);
        $this->assertSame($sentMessages[0]->text, $sentMessages[1]->text);
    }

    /** Без получателей (нет max_manager) отправка не выполняется. */
    public function test_notify_manual_order_skips_send_when_no_recipients(): void
    {
        $client = $this->createMock(MaxMessengerClientInterface::class);
        $client->expects($this->never())->method('sendMessage');
        $client->expects($this->never())->method('sendInlineKeyboardMessage');

        $recipientResolver = $this->createMock(OrderCustomerNotifyRecipientResolverInterface::class);
        $recipientResolver
            ->expects($this->once())
            ->method('resolveMaxUserIds')
            ->willReturn([]);

        $notifier = $this->makeNotifier($client, $recipientResolver);

        $notifier->notifyConfirmed($this->makeOrder(id: 42, maxUserId: 1002, isManual: true));
    }

    /** Логирует предупреждение, когда отправка не удалась. */
    public function test_notify_logs_warning_when_send_fails(): void
    {
        $captured = [];

        Log::channel('max_log')->listen(function (MessageLogged $event) use (&$captured): void {
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
    private function makeNotifier(
        MaxMessengerClientInterface $client,
        ?OrderCustomerNotifyRecipientResolverInterface $recipientResolver = null,
        ?MaxUiStandRecipientResolverInterface $uiStandRecipientResolver = null,
    ): LaravelFoodOrderCustomerNotifier {
        return new LaravelFoodOrderCustomerNotifier(
            messageBuilder: $this->messageBuilder,
            openAppButtonFactory: $this->app->make(MaxOpenAppButtonFactory::class),
            recipientResolver: $recipientResolver
                ?? $this->app->make(OrderCustomerNotifyRecipientResolverInterface::class),
            uiStandRecipientResolver: $uiStandRecipientResolver
                ?? $this->app->make(MaxUiStandRecipientResolverInterface::class),
            notificationSender: $this->makeNotificationSender($client),
        );
    }

    /** Создаёт sender с подставным MAX-клиентом. */
    private function makeNotificationSender(MaxMessengerClientInterface $client): MaxMessengerNotificationSenderInterface
    {
        return new MaxMessengerNotificationSender(
            $client,
            $this->app->make(MaxUiStandRecipientResolverInterface::class),
            Log::channel('max_log'),
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
        bool $isManual = false,
        ?int $createdByMaxUserId = null,
        ?string $createdAt = null,
        ?string $customerFirstName = null,
        ?string $customerLastName = null,
    ): FoodOrderRecord {
        return new FoodOrderRecord(
            id: $id,
            cartId: null,
            maxUserId: $maxUserId,
            isManual: $isManual,
            createdByMaxUserId: $createdByMaxUserId,
            restaurantId: 1,
            status: OrderStatus::PendingReview,
            addressReviewStatus: OrderReviewStatus::Pending,
            compositionReviewStatus: OrderReviewStatus::Pending,
            paymentReviewStatus: OrderReviewStatus::Pending,
            addressReviewedBy: null,
            addressReviewedAt: null,
            compositionReviewedBy: null,
            compositionReviewedAt: null,
            addressRejectionComment: $addressRejectionComment,
            compositionRejectionComment: $compositionRejectionComment,
            paymentReviewedBy: null,
            paymentReviewedAt: null,
            paymentRejectionComment: null,
            total: $total ?? '0.00',
            deliveryAddress: $deliveryAddress,
            deliveryDate: null,
            deliveryCost: $deliveryCost,
            itemsTotal: $itemsTotal ?? '0.00',
            itemsSnapshot: $itemsSnapshot ?? [],
            createdAt: $createdAt ?? now()->toIso8601String(),
            updatedAt: null,
            restaurantName: $restaurantName,
            customerFirstName: $customerFirstName,
            customerLastName: $customerLastName,
        );
    }
}
