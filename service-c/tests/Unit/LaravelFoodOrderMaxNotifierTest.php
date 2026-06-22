<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Contracts\Max\MaxOrderNotificationConfigProviderInterface;
use App\DTO\Food\OrderDto;
use App\DTO\Max\MaxOrderNotificationConfig;
use App\Models\MaxUser;
use App\Services\Food\FoodOrderMaxMessageBuilder;
use App\Services\Food\LaravelFoodOrderMaxNotifier;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Log;
use Shared\MaxMessenger\Contracts\MaxMessengerClientInterface;
use Shared\MaxMessenger\DTO\MaxMessageDto;
use Shared\MaxMessenger\Exceptions\MaxMessengerRequestException;
use Tests\Support\MessMaxLogTestHelper;
use Tests\TestCase;

class LaravelFoodOrderMaxNotifierTest extends TestCase
{
    public function test_notify_calls_send_message_for_each_recipient(): void
    {
        $sentMessages = [];
        $client = $this->createMock(MaxMessengerClientInterface::class);
        $client
            ->expects($this->exactly(3))
            ->method('sendMessage')
            ->willReturnCallback(function (MaxMessageDto $message) use (&$sentMessages): void {
                $sentMessages[] = $message;
            });

        $notifier = new LaravelFoodOrderMaxNotifier(
            client: $client,
            configProvider: $this->makeConfigProvider(new MaxOrderNotificationConfig(
                chatIds: [111, 222],
                userIds: [333],
                maxTextLength: 4000,
            )),
            messageBuilder: new FoodOrderMaxMessageBuilder,
        );

        $notifier->notify($this->makeOrder(), $this->makeMaxUser());

        $this->assertCount(3, $sentMessages);
        $this->assertSame(
            [111, 222],
            array_map(
                static fn (MaxMessageDto $dto): ?int => $dto->chatId,
                array_filter($sentMessages, static fn (MaxMessageDto $dto): bool => $dto->chatId !== null),
            ),
        );
        $this->assertSame(333, $sentMessages[2]->userId);
        $this->assertNull($sentMessages[2]->chatId);
        $this->assertStringContainsString('Новая заявка №42', $sentMessages[0]->text);
        $this->assertStringContainsString('Ресторан: Пиццерия', $sentMessages[0]->text);
        $this->assertSame($sentMessages[0]->text, $sentMessages[1]->text);
        $this->assertSame($sentMessages[0]->text, $sentMessages[2]->text);
        $this->assertNull($sentMessages[0]->fileAttachmentToken);
    }

    public function test_notify_logs_warning_and_continues_when_one_recipient_fails(): void
    {
        $captured = [];

        Log::channel('messMax')->listen(function (MessageLogged $event) use (&$captured): void {
            $captured[] = $event;
        });

        $client = $this->createMock(MaxMessengerClientInterface::class);
        $client
            ->expects($this->exactly(2))
            ->method('sendMessage')
            ->willReturnCallback(function (MaxMessageDto $message): void {
                if ($message->chatId === 111) {
                    throw new MaxMessengerRequestException('Chat not found');
                }
            });

        $notifier = new LaravelFoodOrderMaxNotifier(
            client: $client,
            configProvider: $this->makeConfigProvider(new MaxOrderNotificationConfig(
                chatIds: [111, 222],
                userIds: [],
                maxTextLength: 4000,
            )),
            messageBuilder: new FoodOrderMaxMessageBuilder,
        );

        $notifier->notify($this->makeOrder(), $this->makeMaxUser());

        $log = MessMaxLogTestHelper::assertSingleMessage($captured, 'MAX order notification send failed');
        $this->assertSame('warning', $log->level);
        $this->assertSame(42, $log->context['order_id']);
        $this->assertSame(111, $log->context['chat_id']);
        $this->assertNull($log->context['user_id']);
        $this->assertSame('Chat not found', $log->context['error']);
    }

    public function test_notify_calls_recipients_sequentially(): void
    {
        $callOrder = [];
        $client = $this->createMock(MaxMessengerClientInterface::class);
        $client
            ->expects($this->exactly(2))
            ->method('sendMessage')
            ->willReturnCallback(function (MaxMessageDto $message) use (&$callOrder): void {
                $callOrder[] = $message->chatId ?? $message->userId;
            });

        $notifier = new LaravelFoodOrderMaxNotifier(
            client: $client,
            configProvider: $this->makeConfigProvider(new MaxOrderNotificationConfig(
                chatIds: [10, 20],
                userIds: [],
                maxTextLength: 4000,
            )),
            messageBuilder: new FoodOrderMaxMessageBuilder,
        );

        $notifier->notify($this->makeOrder(), $this->makeMaxUser());

        $this->assertSame([10, 20], $callOrder);
    }

    private function makeConfigProvider(MaxOrderNotificationConfig $config): MaxOrderNotificationConfigProviderInterface
    {
        $provider = $this->createMock(MaxOrderNotificationConfigProviderInterface::class);
        $provider->method('config')->willReturn($config);

        return $provider;
    }

    private function makeOrder(): OrderDto
    {
        return new OrderDto(
            id: 42,
            status: 'submitted',
            restaurantId: 1,
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
            ],
            createdAt: '2026-06-22T12:00:00+00:00',
        );
    }

    private function makeMaxUser(): MaxUser
    {
        return new MaxUser([
            'max_user_id' => 1002,
            'first_name' => 'Иван',
            'username' => 'ivan',
        ]);
    }
}
