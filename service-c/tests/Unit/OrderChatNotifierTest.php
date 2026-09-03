<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Contracts\Food\Review\OrderCustomerNotifyRecipientResolverInterface;
use App\DTO\Food\Chat\OrderMessageDto;
use App\DTO\Food\Order\FoodOrderRecord;
use App\Enums\Food\Chat\OrderMessageAuthorType;
use App\Enums\Food\Order\OrderStatus;
use App\Enums\Food\Review\OrderReviewStatus;
use App\Services\Max\Food\FoodOrderMaxMessageBuilder;
use App\Infrastructure\Laravel\LaravelOrderChatNotifier;
use App\Support\Food\Composition\OrderSnapshotComboResolver;
use App\Contracts\Max\MaxMessengerNotificationSenderInterface;
use App\Services\Max\MaxMessengerNotificationSender;
use App\Support\Max\MaxOpenAppButtonFactory;
use App\Support\Max\MaxUiStandRecipientResolver;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Shared\MaxMessenger\Contracts\MaxMessengerClientInterface;
use Shared\MaxMessenger\DTO\MaxInlineKeyboardMessageDto;
use Shared\MaxMessenger\DTO\MaxMessageDto;
use Shared\MaxMessenger\Exceptions\MaxMessengerRequestException;
use Tests\Support\MessMaxLogTestHelper;
use Tests\TestCase;

class OrderChatNotifierTest extends TestCase
{
    private FoodOrderMaxMessageBuilder $messageBuilder;

    /** Подготовка окружения перед тестом. */
    protected function setUp(): void
    {
        parent::setUp();

        Cache::forget('max.ui_stand.known_recipients');
        Config::set('max.ui_stand.recipient_chat_ids', []);
        Config::set('max.ui_stand.recipient_user_ids', []);

        $this->messageBuilder = new FoodOrderMaxMessageBuilder(new OrderSnapshotComboResolver);
    }

    /** Короткое уведомление клиенту без текста сообщения. */
    public function test_build_order_chat_customer_notification(): void
    {
        $order = $this->makeOrder(id: 42);

        $this->assertSame(
            'В чат заказа №42 поступило сообщение',
            $this->messageBuilder->buildOrderChatCustomerNotification($order),
        );
    }

    /** Уведомление UI Stand содержит заголовок и текст сообщения. */
    public function test_build_order_chat_ui_stand_notification_includes_body(): void
    {
        $order = $this->makeOrder(id: 42);
        $message = $this->makeMessageDto(
            foodOrderId: 42,
            body: 'Уточните адрес, подъезд 3',
            authorType: OrderMessageAuthorType::Customer,
        );

        $text = $this->messageBuilder->buildOrderChatUiStandNotification($order, $message);

        $this->assertSame(
            <<<'TEXT'
В чат заказа №42 поступило сообщение
Уточните адрес, подъезд 3
TEXT,
            $text,
        );
    }

    /** Уведомление UI Stand обрезает длинный body. */
    public function test_build_order_chat_ui_stand_notification_truncates_long_body(): void
    {
        $order = $this->makeOrder(id: 5);
        $message = $this->makeMessageDto(
            foodOrderId: 5,
            body: str_repeat('а', 250),
            authorType: OrderMessageAuthorType::Admin,
        );

        $text = $this->messageBuilder->buildOrderChatUiStandNotification($order, $message);
        $lines = explode("\n", $text);

        $this->assertSame('В чат заказа №5 поступило сообщение', $lines[0]);
        $this->assertSame(200, mb_strlen($lines[1]));
        $this->assertStringEndsWith('…', $lines[1]);
    }

    /** Open-app start_param чата заказа. */
    public function test_build_order_chat_start_param(): void
    {
        $this->assertSame('order_14_chat', $this->messageBuilder->buildOrderChatStartParam(14));
        $this->assertSame('order_42_chat', $this->messageBuilder->buildOrderChatStartParam(42));
    }

    /** Open-app URL чата добавляет query-параметры (локальный fallback). */
    public function test_build_order_chat_open_app_url_appends_query_params(): void
    {
        $url = $this->messageBuilder->buildOrderChatOpenAppUrl(
            orderId: 42,
            baseWebAppUrl: 'https://example.test/max-app',
        );

        $this->assertSame('https://example.test/max-app?order_id=42&view=chat', $url);
    }

    /** Open-app URL чата возвращает null, если base отсутствует. */
    public function test_build_order_chat_open_app_url_returns_null_when_base_missing(): void
    {
        $this->assertNull($this->messageBuilder->buildOrderChatOpenAppUrl(42, null));
        $this->assertNull($this->messageBuilder->buildOrderChatOpenAppUrl(42, '   '));
    }

    /** Сообщение клиента уходит только в UI Stand, клиенту не дублируется. */
    public function test_notify_customer_message_sends_only_to_ui_stand(): void
    {
        Config::set('max.ui_stand.mini_app_url', 'https://example.test/max-app');
        Config::set('max.bot_user_id', 421816864057);
        Config::set('max.ui_stand.recipient_chat_ids', [777001, 777002]);
        Config::set('max.ui_stand.recipient_user_ids', []);

        $sentMessages = [];
        $client = $this->createMock(MaxMessengerClientInterface::class);
        $client
            ->expects($this->exactly(2))
            ->method('sendInlineKeyboardMessage')
            ->willReturnCallback(function (MaxInlineKeyboardMessageDto $message) use (&$sentMessages): void {
                $sentMessages[] = $message;
            });
        $client->expects($this->never())->method('sendMessage');

        $notifier = $this->makeNotifier($client);

        $order = $this->makeOrder(id: 42, maxUserId: 1002);
        $message = $this->makeMessageDto(
            foodOrderId: 42,
            body: 'Уточните адрес, подъезд 3',
            authorType: OrderMessageAuthorType::Customer,
        );

        $notifier->notify($order, $message);

        $this->assertCount(2, $sentMessages);
        $this->assertSame([777001, 777002], array_map(
            static fn (MaxInlineKeyboardMessageDto $dto): int => (int) $dto->chatId,
            $sentMessages,
        ));

        $first = $sentMessages[0];
        $this->assertStringContainsString('В чат заказа №42 поступило сообщение', $first->text);
        $this->assertStringContainsString('Уточните адрес, подъезд 3', $first->text);
        $this->assertSame('open_app', $first->buttonRows[0][0]->type);
        $this->assertSame('Открыть заказ №42', $first->buttonRows[0][0]->text);
        $this->assertSame('https://example.test/max-app', $first->buttonRows[0][0]->webApp);
        $this->assertSame('order_42_chat', $first->buttonRows[0][0]->payload);
    }

    /** Сообщение админа уходит клиенту (без body) и в UI Stand (с body). */
    public function test_notify_admin_message_sends_to_customer_and_ui_stand(): void
    {
        $this->disableOpenAppTarget();
        Config::set('max.ui_stand.recipient_chat_ids', [777001]);
        Config::set('max.ui_stand.recipient_user_ids', []);

        $sentMessages = [];
        $client = $this->createMock(MaxMessengerClientInterface::class);
        $client
            ->expects($this->exactly(2))
            ->method('sendMessage')
            ->willReturnCallback(function (MaxMessageDto $message) use (&$sentMessages): void {
                $sentMessages[] = $message;
            });
        $client->expects($this->never())->method('sendInlineKeyboardMessage');

        $notifier = $this->makeNotifier($client);

        $order = $this->makeOrder(id: 7, maxUserId: 1002);
        $message = $this->makeMessageDto(
            foodOrderId: 7,
            body: 'Принято, уточняем доставку',
            authorType: OrderMessageAuthorType::Admin,
        );

        $notifier->notify($order, $message);

        $this->assertCount(2, $sentMessages);

        $uiStand = $sentMessages[0];
        $this->assertSame(777001, $uiStand->chatId);
        $this->assertNull($uiStand->userId);
        $this->assertSame(
            "В чат заказа №7 поступило сообщение\nПринято, уточняем доставку",
            $uiStand->text,
        );

        $customer = $sentMessages[1];
        $this->assertSame(1002, $customer->userId);
        $this->assertNull($customer->chatId);
        $this->assertSame('В чат заказа №7 поступило сообщение', $customer->text);
        $this->assertStringNotContainsString('Принято, уточняем доставку', $customer->text);
    }

    /** Логирует предупреждение при ошибке отправки без исключения. */
    public function test_notify_logs_warning_when_send_fails_without_throwing(): void
    {
        $this->disableOpenAppTarget();
        Config::set('max.ui_stand.recipient_chat_ids', []);
        Config::set('max.ui_stand.recipient_user_ids', []);

        $captured = [];

        Log::channel('max_log')->listen(function (MessageLogged $event) use (&$captured): void {
            $captured[] = $event;
        });

        $client = $this->createMock(MaxMessengerClientInterface::class);
        $client->expects($this->once())->method('sendMessage')
            ->willThrowException(new MaxMessengerRequestException('User blocked bot'));

        $notifier = $this->makeNotifier($client);

        $order = $this->makeOrder(id: 3, maxUserId: 99);
        $message = $this->makeMessageDto(
            foodOrderId: 3,
            body: 'Ответ админа',
            authorType: OrderMessageAuthorType::Admin,
        );

        $notifier->notify($order, $message);

        MessMaxLogTestHelper::assertSingleMessage(
            $captured,
            'MAX order chat notification skipped: UI Stand recipients are not configured',
        );

        $failLog = MessMaxLogTestHelper::assertSingleMessage(
            $captured,
            'MAX order chat notification send failed',
        );
        $this->assertSame('warning', $failLog->level);
        $this->assertSame(3, $failLog->context['order_id']);
        $this->assertSame(99, $failLog->context['max_user_id']);
        $this->assertSame('User blocked bot', $failLog->context['error']);
    }

    /** Логирует, если UI Stand не настроен, при сообщении клиента. */
    public function test_notify_customer_message_logs_when_ui_stand_not_configured(): void
    {
        $captured = [];

        Log::channel('max_log')->listen(function (MessageLogged $event) use (&$captured): void {
            $captured[] = $event;
        });

        Config::set('max.ui_stand.recipient_chat_ids', []);
        Config::set('max.ui_stand.recipient_user_ids', []);

        $client = $this->createMock(MaxMessengerClientInterface::class);
        $client->expects($this->never())->method('sendMessage');
        $client->expects($this->never())->method('sendInlineKeyboardMessage');

        $notifier = $this->makeNotifier($client);

        $order = $this->makeOrder(id: 11, maxUserId: 1002);
        $message = $this->makeMessageDto(
            foodOrderId: 11,
            body: 'Где мой заказ?',
            authorType: OrderMessageAuthorType::Customer,
        );

        $notifier->notify($order, $message);

        $log = MessMaxLogTestHelper::assertSingleMessage(
            $captured,
            'MAX order chat notification skipped: UI Stand recipients are not configured',
        );
        $this->assertSame('warning', $log->level);
        $this->assertSame(11, $log->context['order_id']);
        $this->assertSame('customer', $log->context['author_type']);
    }

    /** Для ручного заказа сообщение админа уходит менеджерам, не клиенту. */
    public function test_notify_admin_message_manual_order_sends_to_managers_not_customer(): void
    {
        $this->disableOpenAppTarget();
        Config::set('max.ui_stand.recipient_chat_ids', []);
        Config::set('max.ui_stand.recipient_user_ids', []);

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

        $order = $this->makeOrder(id: 7, maxUserId: 1002, isManual: true);
        $message = $this->makeMessageDto(
            foodOrderId: 7,
            body: 'Принято, уточняем доставку',
            authorType: OrderMessageAuthorType::Admin,
        );

        $notifier->notify($order, $message);

        $this->assertSame([9001, 9002], $sentUserIds);
        $this->assertNotContains(1002, $sentUserIds);
    }

    /** Создаёт notifier с подставным MAX-клиентом. */
    private function makeNotifier(
        MaxMessengerClientInterface $client,
        ?OrderCustomerNotifyRecipientResolverInterface $customerRecipientResolver = null,
    ): LaravelOrderChatNotifier {
        return new LaravelOrderChatNotifier(
            messageBuilder: $this->messageBuilder,
            uiStandRecipientResolver: $this->app->make(MaxUiStandRecipientResolver::class),
            openAppButtonFactory: $this->app->make(MaxOpenAppButtonFactory::class),
            customerRecipientResolver: $customerRecipientResolver
                ?? $this->app->make(OrderCustomerNotifyRecipientResolverInterface::class),
            notificationSender: $this->makeNotificationSender($client),
        );
    }

    /** Создаёт sender с подставным MAX-клиентом. */
    private function makeNotificationSender(MaxMessengerClientInterface $client): MaxMessengerNotificationSenderInterface
    {
        return new MaxMessengerNotificationSender(
            $client,
            $this->app->make(MaxUiStandRecipientResolver::class),
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
    private function makeOrder(int $id, int $maxUserId = 1000, bool $isManual = false): FoodOrderRecord
    {
        return new FoodOrderRecord(
            id: $id,
            cartId: null,
            maxUserId: $maxUserId,
            isManual: $isManual,
            createdByMaxUserId: null,
            restaurantId: 1,
            status: OrderStatus::PendingReview,
            addressReviewStatus: OrderReviewStatus::Pending,
            compositionReviewStatus: OrderReviewStatus::Pending,
            paymentReviewStatus: OrderReviewStatus::Pending,
            addressReviewedBy: null,
            addressReviewedAt: null,
            compositionReviewedBy: null,
            compositionReviewedAt: null,
            addressRejectionComment: null,
            compositionRejectionComment: null,
            paymentReviewedBy: null,
            paymentReviewedAt: null,
            paymentRejectionComment: null,
            total: '0.00',
            deliveryAddress: null,
            deliveryDate: null,
            deliveryCost: null,
            itemsTotal: '0.00',
            itemsSnapshot: [],
            createdAt: '2026-06-24T12:00:00+00:00',
            updatedAt: null,
        );
    }

    /** Создаёт DTO сообщения чата. */
    private function makeMessageDto(
        int $foodOrderId,
        string $body,
        OrderMessageAuthorType $authorType,
        ?string $senderFirstName = null,
        ?string $senderLastName = null,
        ?string $senderUsername = null,
        int $id = 1,
        int $senderMaxUserId = 1000,
    ): OrderMessageDto {
        return new OrderMessageDto(
            id: $id,
            foodOrderId: $foodOrderId,
            senderMaxUserId: $senderMaxUserId,
            senderFirstName: $senderFirstName,
            senderLastName: $senderLastName,
            senderUsername: $senderUsername,
            authorType: $authorType,
            body: $body,
            createdAt: '2026-06-24T12:00:00+00:00',
        );
    }
}
