<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Contracts\Food\FoodOrderAdminRepositoryInterface;
use App\DTO\Food\OrderMessageDto;
use App\Enums\Food\OrderMessageAuthorType;
use App\Models\FoodOrder;
use App\Services\Food\FoodOrderMaxMessageBuilder;
use App\Services\Food\LaravelOrderChatNotifier;
use App\Support\MaxOpenAppTargetResolver;
use Illuminate\Log\Events\MessageLogged;
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

    protected function setUp(): void
    {
        parent::setUp();

        $this->messageBuilder = new FoodOrderMaxMessageBuilder;
    }

    public function test_build_order_chat_notification_formats_sender_and_body(): void
    {
        $order = $this->makeOrder(id: 42);
        $message = $this->makeMessageDto(
            foodOrderId: 42,
            senderFirstName: 'Иван',
            body: 'Уточните адрес, подъезд 3',
            authorType: OrderMessageAuthorType::Customer,
        );

        $text = $this->messageBuilder->buildOrderChatNotification($order, $message);

        $this->assertSame(
            <<<'TEXT'
Новое сообщение по заказу №42
Иван: Уточните адрес, подъезд 3
TEXT,
            $text,
        );
    }

    public function test_build_order_chat_notification_truncates_long_body(): void
    {
        $order = $this->makeOrder(id: 5);
        $message = $this->makeMessageDto(
            foodOrderId: 5,
            senderUsername: 'support',
            body: str_repeat('а', 250),
            authorType: OrderMessageAuthorType::Admin,
        );

        $text = $this->messageBuilder->buildOrderChatNotification($order, $message);
        $lines = explode("\n", $text);

        $this->assertSame('Новое сообщение по заказу №5', $lines[0]);
        $this->assertStringStartsWith('@support: ', $lines[1]);
        $this->assertSame(200, mb_strlen(mb_substr($lines[1], mb_strlen('@support: '))));
        $this->assertStringEndsWith('…', $lines[1]);
    }

    public function test_build_order_chat_open_app_url_appends_query_params(): void
    {
        $url = $this->messageBuilder->buildOrderChatOpenAppUrl(
            orderId: 42,
            baseWebAppUrl: 'https://example.test/max-app',
        );

        $this->assertSame('https://example.test/max-app?order_id=42&view=chat', $url);
    }

    public function test_build_order_chat_open_app_url_returns_null_when_base_missing(): void
    {
        $this->assertNull($this->messageBuilder->buildOrderChatOpenAppUrl(42, null));
        $this->assertNull($this->messageBuilder->buildOrderChatOpenAppUrl(42, '   '));
    }

    public function test_notify_customer_message_sends_to_all_active_admins_with_open_app_button(): void
    {
        Config::set('max.ui_stand.mini_app_url', 'https://example.test/max-app');
        Config::set('max.bot_user_id', 421816864057);

        $sentMessages = [];
        $client = $this->createMock(MaxMessengerClientInterface::class);
        $client
            ->expects($this->exactly(2))
            ->method('sendInlineKeyboardMessage')
            ->willReturnCallback(function (MaxInlineKeyboardMessageDto $message) use (&$sentMessages): void {
                $sentMessages[] = $message;
            });
        $client->expects($this->never())->method('sendMessage');

        $adminRepository = $this->createMock(FoodOrderAdminRepositoryInterface::class);
        $adminRepository
            ->expects($this->once())
            ->method('listActiveAdminMaxUserIds')
            ->willReturn([1001, 1003]);

        $notifier = new LaravelOrderChatNotifier(
            client: $client,
            messageBuilder: $this->messageBuilder,
            foodOrderAdminRepository: $adminRepository,
            openAppTargetResolver: $this->app->make(MaxOpenAppTargetResolver::class),
        );

        $order = $this->makeOrder(id: 42, maxUserId: 1002);
        $message = $this->makeMessageDto(
            foodOrderId: 42,
            senderFirstName: 'Иван',
            body: 'Уточните адрес, подъезд 3',
            authorType: OrderMessageAuthorType::Customer,
        );

        $notifier->notify($order, $message);

        $this->assertCount(2, $sentMessages);
        $this->assertSame([1001, 1003], array_map(
            static fn (MaxInlineKeyboardMessageDto $dto): int => (int) $dto->userId,
            $sentMessages,
        ));

        $first = $sentMessages[0];
        $this->assertStringContainsString('Новое сообщение по заказу №42', $first->text);
        $this->assertStringContainsString('Иван: Уточните адрес, подъезд 3', $first->text);
        $this->assertSame('open_app', $first->buttonRows[0][0]->type);
        $this->assertSame('Открыть в приложении', $first->buttonRows[0][0]->text);
        $this->assertSame(
            'https://example.test/max-app?order_id=42&view=chat',
            $first->buttonRows[0][0]->webApp,
        );
        $this->assertSame(421816864057, $first->buttonRows[0][0]->contactId);
    }

    public function test_notify_admin_message_sends_to_order_customer(): void
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

        $adminRepository = $this->createMock(FoodOrderAdminRepositoryInterface::class);
        $adminRepository->expects($this->never())->method('listActiveAdminMaxUserIds');

        $notifier = new LaravelOrderChatNotifier(
            client: $client,
            messageBuilder: $this->messageBuilder,
            foodOrderAdminRepository: $adminRepository,
            openAppTargetResolver: $this->app->make(MaxOpenAppTargetResolver::class),
        );

        $order = $this->makeOrder(id: 7, maxUserId: 1002);
        $message = $this->makeMessageDto(
            foodOrderId: 7,
            senderFirstName: 'Админ',
            body: 'Принято, уточняем доставку',
            authorType: OrderMessageAuthorType::Admin,
        );

        $notifier->notify($order, $message);

        $this->assertNotNull($sentMessage);
        $this->assertSame(1002, $sentMessage->userId);
        $this->assertStringContainsString('Новое сообщение по заказу №7', $sentMessage->text);
        $this->assertStringContainsString('Админ: Принято, уточняем доставку', $sentMessage->text);
    }

    public function test_notify_logs_warning_when_send_fails_without_throwing(): void
    {
        $this->disableOpenAppTarget();

        $captured = [];

        Log::channel('messMax')->listen(function (MessageLogged $event) use (&$captured): void {
            $captured[] = $event;
        });

        $client = $this->createMock(MaxMessengerClientInterface::class);
        $client
            ->expects($this->once())
            ->method('sendMessage')
            ->willThrowException(new MaxMessengerRequestException('User blocked bot'));

        $adminRepository = $this->createMock(FoodOrderAdminRepositoryInterface::class);
        $adminRepository->expects($this->never())->method('listActiveAdminMaxUserIds');

        $notifier = new LaravelOrderChatNotifier(
            client: $client,
            messageBuilder: $this->messageBuilder,
            foodOrderAdminRepository: $adminRepository,
            openAppTargetResolver: $this->app->make(MaxOpenAppTargetResolver::class),
        );

        $order = $this->makeOrder(id: 3, maxUserId: 99);
        $message = $this->makeMessageDto(
            foodOrderId: 3,
            body: 'Ответ админа',
            authorType: OrderMessageAuthorType::Admin,
        );

        $notifier->notify($order, $message);

        $log = MessMaxLogTestHelper::assertSingleMessage($captured, 'MAX order chat notification send failed');
        $this->assertSame('warning', $log->level);
        $this->assertSame(3, $log->context['order_id']);
        $this->assertSame($message->id, $log->context['message_id']);
        $this->assertSame(99, $log->context['max_user_id']);
        $this->assertSame('User blocked bot', $log->context['error']);
    }

    public function test_notify_customer_message_logs_when_no_active_admins(): void
    {
        $captured = [];

        Log::channel('messMax')->listen(function (MessageLogged $event) use (&$captured): void {
            $captured[] = $event;
        });

        $client = $this->createMock(MaxMessengerClientInterface::class);
        $client->expects($this->never())->method('sendMessage');
        $client->expects($this->never())->method('sendInlineKeyboardMessage');

        $adminRepository = $this->createMock(FoodOrderAdminRepositoryInterface::class);
        $adminRepository
            ->expects($this->once())
            ->method('listActiveAdminMaxUserIds')
            ->willReturn([]);

        $notifier = new LaravelOrderChatNotifier(
            client: $client,
            messageBuilder: $this->messageBuilder,
            foodOrderAdminRepository: $adminRepository,
            openAppTargetResolver: $this->app->make(MaxOpenAppTargetResolver::class),
        );

        $order = $this->makeOrder(id: 11, maxUserId: 1002);
        $message = $this->makeMessageDto(
            foodOrderId: 11,
            body: 'Где мой заказ?',
            authorType: OrderMessageAuthorType::Customer,
        );

        $notifier->notify($order, $message);

        $log = MessMaxLogTestHelper::assertSingleMessage($captured, 'MAX order chat notification skipped: no recipients');
        $this->assertSame('warning', $log->level);
        $this->assertSame(11, $log->context['order_id']);
        $this->assertSame('customer', $log->context['author_type']);
    }

    private function disableOpenAppTarget(): void
    {
        Config::set('max.ui_stand.mini_app_url', '');
        Config::set('max.public_app_url', '');
        Config::set('max.webhook.url', '');
        Config::set('max.bot_username', '');
    }

    private function makeOrder(int $id, int $maxUserId = 1000): FoodOrder
    {
        $order = new FoodOrder([
            'max_user_id' => $maxUserId,
        ]);
        $order->id = $id;

        return $order;
    }

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
