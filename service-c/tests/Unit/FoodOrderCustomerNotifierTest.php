<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\Food\OrderRejectionScope;
use App\Models\FoodOrder;
use App\Services\Food\FoodOrderMaxMessageBuilder;
use App\Services\Food\LaravelFoodOrderCustomerNotifier;
use App\Support\OrderSnapshotComboResolver;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Log;
use Shared\MaxMessenger\Contracts\MaxMessengerClientInterface;
use Shared\MaxMessenger\DTO\MaxMessageDto;
use Shared\MaxMessenger\Exceptions\MaxMessengerRequestException;
use Tests\Support\MessMaxLogTestHelper;
use Tests\TestCase;

class FoodOrderCustomerNotifierTest extends TestCase
{
    private FoodOrderMaxMessageBuilder $messageBuilder;

    protected function setUp(): void
    {
        parent::setUp();

        $this->messageBuilder = new FoodOrderMaxMessageBuilder(new OrderSnapshotComboResolver);
    }

    public function test_build_customer_submitted_message(): void
    {
        $order = $this->makeOrder(id: 11);

        $text = $this->messageBuilder->buildCustomerSubmitted($order);

        $this->assertSame(
            'Заказ принят на рассмотрение. В чате заказа можете сделать уточнения к заказу',
            $text,
        );
    }

    public function test_build_customer_confirmed_message(): void
    {
        $order = $this->makeOrder(id: 15);

        $text = $this->messageBuilder->buildCustomerConfirmed($order);

        $this->assertSame('Заявка №15 принята к исполнению', $text);
    }

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

    public function test_notify_submitted_sends_message_to_order_customer(): void
    {
        $sentMessage = null;
        $client = $this->createMock(MaxMessengerClientInterface::class);
        $client
            ->expects($this->once())
            ->method('sendMessage')
            ->willReturnCallback(function (MaxMessageDto $message) use (&$sentMessage): void {
                $sentMessage = $message;
            });

        $notifier = new LaravelFoodOrderCustomerNotifier(
            client: $client,
            messageBuilder: $this->messageBuilder,
        );

        $order = $this->makeOrder(id: 33, maxUserId: 2001);

        $notifier->notifySubmitted($order);

        $this->assertNotNull($sentMessage);
        $this->assertSame(2001, $sentMessage->userId);
        $this->assertNull($sentMessage->chatId);
        $this->assertSame(
            'Заказ принят на рассмотрение. В чате заказа можете сделать уточнения к заказу',
            $sentMessage->text,
        );
    }

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

        $notifier = new LaravelFoodOrderCustomerNotifier(
            client: $client,
            messageBuilder: $this->messageBuilder,
        );

        $order = $this->makeOrder(id: 42, maxUserId: 1002);

        $notifier->notifyConfirmed($order);

        $this->assertNotNull($sentMessage);
        $this->assertSame(1002, $sentMessage->userId);
        $this->assertNull($sentMessage->chatId);
        $this->assertSame('Заявка №42 принята к исполнению', $sentMessage->text);
    }

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

        $notifier = new LaravelFoodOrderCustomerNotifier(
            client: $client,
            messageBuilder: $this->messageBuilder,
        );

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

        $notifier = new LaravelFoodOrderCustomerNotifier(
            client: $client,
            messageBuilder: $this->messageBuilder,
        );

        $order = $this->makeOrder(id: 3, maxUserId: 99);

        $notifier->notifyConfirmed($order);

        $log = MessMaxLogTestHelper::assertSingleMessage($captured, 'MAX customer order notification send failed');
        $this->assertSame('warning', $log->level);
        $this->assertSame(3, $log->context['order_id']);
        $this->assertSame(99, $log->context['max_user_id']);
        $this->assertSame('User blocked bot', $log->context['error']);
    }

    private function makeOrder(
        int $id,
        int $maxUserId = 1000,
        ?string $addressRejectionComment = null,
        ?string $compositionRejectionComment = null,
    ): FoodOrder {
        $order = new FoodOrder([
            'max_user_id' => $maxUserId,
            'address_rejection_comment' => $addressRejectionComment,
            'composition_rejection_comment' => $compositionRejectionComment,
        ]);
        $order->id = $id;

        return $order;
    }
}
