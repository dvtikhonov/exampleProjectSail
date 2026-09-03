<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Contracts\Max\MaxMessengerNotificationSenderInterface;
use App\Contracts\Max\MaxOrderNotificationConfigProviderInterface;
use App\Contracts\Max\MaxUiStandRecipientResolverInterface;
use App\DTO\Food\Order\OrderDto;
use App\DTO\Food\Shared\MaxUserDisplayDto;
use App\DTO\Max\MaxOrderNotificationConfig;
use App\Infrastructure\Laravel\LaravelFoodOrderMaxNotifier;
use App\Services\Max\Food\FoodOrderMaxMessageBuilder;
use App\Services\Max\MaxMessengerNotificationSender;
use App\Support\Food\Composition\OrderSnapshotComboResolver;
use App\Support\Max\MaxOpenAppButtonFactory;
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

class LaravelFoodOrderMaxNotifierTest extends TestCase
{
    /** Сбрасывает кэш webhook-получателей UI Stand (не тянуть chatId из других тестов). */
    protected function setUp(): void
    {
        parent::setUp();

        Cache::forget('max.ui_stand.known_recipients');
        Config::set('max.ui_stand.recipient_chat_ids', []);
        Config::set('max.ui_stand.recipient_user_ids', []);
    }

    /** Notify шлёт inline-keyboard каждому получателю, если mini-app настроен. */
    public function test_notify_calls_send_inline_keyboard_for_each_recipient_when_mini_app_configured(): void
    {
        Config::set('max.ui_stand.mini_app_url', 'https://example.test/max-app');
        Config::set('max.bot_user_id', 421816864057);

        $sentMessages = [];
        $client = $this->createMock(MaxMessengerClientInterface::class);
        $client
            ->expects($this->exactly(3))
            ->method('sendInlineKeyboardMessage')
            ->willReturnCallback(function (MaxInlineKeyboardMessageDto $message) use (&$sentMessages): void {
                $sentMessages[] = $message;
            });
        $client->expects($this->never())->method('sendMessage');

        $notifier = $this->makeNotifier(
            $client,
            new MaxOrderNotificationConfig(chatIds: [], userIds: [], maxTextLength: 4000),
            chatIds: [111, 222],
            userIds: [333],
        );

        $notifier->notify($this->makeOrder(), $this->makeMaxUser());

        $this->assertCount(3, $sentMessages);
        $this->assertSame(
            [111, 222],
            array_map(
                static fn (MaxInlineKeyboardMessageDto $dto): ?int => $dto->chatId,
                array_filter($sentMessages, static fn (MaxInlineKeyboardMessageDto $dto): bool => $dto->chatId !== null),
            ),
        );
        $this->assertSame(333, $sentMessages[2]->userId);
        $this->assertNull($sentMessages[2]->chatId);
        $this->assertStringContainsString('Новая заявка №42', $sentMessages[0]->text);
        $this->assertStringContainsString('Ресторан: Пиццерия', $sentMessages[0]->text);
        $this->assertSame($sentMessages[0]->text, $sentMessages[1]->text);
        $this->assertSame($sentMessages[0]->text, $sentMessages[2]->text);

        $button = $sentMessages[0]->buttonRows[0][0];
        $this->assertSame('Заказ еды', $button->text);
        $this->assertSame('open_app', $button->type);
        $this->assertSame('https://example.test/max-app', $button->webApp);
        $this->assertSame(421816864057, $button->contactId);
    }

    /** Notify откатывается к обычному сообщению, если URL mini-app отсутствует. */
    public function test_notify_falls_back_to_plain_message_when_mini_app_url_missing(): void
    {
        Config::set('max.ui_stand.mini_app_url', '');
        Config::set('max.public_app_url', '');
        Config::set('max.webhook.url', '');
        Config::set('max.bot_username', '');

        $sentMessages = [];
        $client = $this->createMock(MaxMessengerClientInterface::class);
        $client
            ->expects($this->once())
            ->method('sendMessage')
            ->willReturnCallback(function (MaxMessageDto $message) use (&$sentMessages): void {
                $sentMessages[] = $message;
            });
        $client->expects($this->never())->method('sendInlineKeyboardMessage');

        $notifier = $this->makeNotifier(
            $client,
            new MaxOrderNotificationConfig(chatIds: [], userIds: [], maxTextLength: 4000),
            chatIds: [111],
            userIds: [],
        );

        $notifier->notify($this->makeOrder(), $this->makeMaxUser());

        $this->assertCount(1, $sentMessages);
        $this->assertStringContainsString('Новая заявка №42', $sentMessages[0]->text);
        $this->assertNull($sentMessages[0]->fileAttachmentToken);
    }

    /** Notify логирует предупреждение и продолжает, если один получатель упал. */
    public function test_notify_logs_warning_and_continues_when_one_recipient_fails(): void
    {
        Config::set('max.ui_stand.mini_app_url', 'https://example.test/max-app');

        $captured = [];

        Log::channel('max_log')->listen(function (MessageLogged $event) use (&$captured): void {
            $captured[] = $event;
        });

        $client = $this->createMock(MaxMessengerClientInterface::class);
        $client
            ->expects($this->exactly(2))
            ->method('sendInlineKeyboardMessage')
            ->willReturnCallback(function (MaxInlineKeyboardMessageDto $message): void {
                if ($message->chatId === 111) {
                    throw new MaxMessengerRequestException('Chat not found');
                }
            });

        $notifier = $this->makeNotifier(
            $client,
            new MaxOrderNotificationConfig(chatIds: [], userIds: [], maxTextLength: 4000),
            chatIds: [111, 222],
            userIds: [],
        );

        $notifier->notify($this->makeOrder(), $this->makeMaxUser());

        $log = MessMaxLogTestHelper::assertSingleMessage($captured, 'MAX order notification send failed');
        $this->assertSame('warning', $log->level);
        $this->assertSame(42, $log->context['order_id']);
        $this->assertSame(111, $log->context['chat_id']);
        $this->assertNull($log->context['user_id']);
        $this->assertSame('Chat not found', $log->context['error']);
    }

    /** Notify пропускает отправку, если получатели не настроены. */
    public function test_notify_skips_send_when_recipients_are_not_configured(): void
    {
        $captured = [];

        Log::channel('max_log')->listen(function (MessageLogged $event) use (&$captured): void {
            $captured[] = $event;
        });

        $client = $this->createMock(MaxMessengerClientInterface::class);
        $client->expects($this->never())->method('sendMessage');
        $client->expects($this->never())->method('sendInlineKeyboardMessage');

        $notifier = $this->makeNotifier(
            $client,
            new MaxOrderNotificationConfig(chatIds: [], userIds: [], maxTextLength: 4000),
            chatIds: [],
            userIds: [],
        );

        $notifier->notify($this->makeOrder(), $this->makeMaxUser());

        $log = MessMaxLogTestHelper::assertSingleMessage(
            $captured,
            'MAX order notification skipped: UI Stand recipients are not configured',
        );
        $this->assertSame('warning', $log->level);
        $this->assertSame(42, $log->context['order_id']);
    }

    /** Notify вызывает получателей последовательно. */
    public function test_notify_calls_recipients_sequentially(): void
    {
        Config::set('max.ui_stand.mini_app_url', 'https://example.test/max-app');

        $callOrder = [];
        $client = $this->createMock(MaxMessengerClientInterface::class);
        $client
            ->expects($this->exactly(2))
            ->method('sendInlineKeyboardMessage')
            ->willReturnCallback(function (MaxInlineKeyboardMessageDto $message) use (&$callOrder): void {
                $callOrder[] = $message->chatId ?? $message->userId;
            });

        $notifier = $this->makeNotifier(
            $client,
            new MaxOrderNotificationConfig(chatIds: [], userIds: [], maxTextLength: 4000),
            chatIds: [10, 20],
            userIds: [],
        );

        $notifier->notify($this->makeOrder(), $this->makeMaxUser());

        $this->assertSame([10, 20], $callOrder);
    }

    /** Создаёт тестируемый нотификатор. */
    private function makeNotifier(
        MaxMessengerClientInterface $client,
        MaxOrderNotificationConfig $config,
        array $chatIds = [],
        array $userIds = [],
    ): LaravelFoodOrderMaxNotifier {
        Config::set('max.ui_stand.recipient_chat_ids', $chatIds);
        Config::set('max.ui_stand.recipient_user_ids', $userIds);

        return new LaravelFoodOrderMaxNotifier(
            configProvider: $this->makeConfigProvider($config),
            uiStandRecipientResolver: $this->app->make(MaxUiStandRecipientResolverInterface::class),
            messageBuilder: new FoodOrderMaxMessageBuilder(new OrderSnapshotComboResolver),
            openAppButtonFactory: $this->app->make(MaxOpenAppButtonFactory::class),
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

    /** Создаёт провайдер конфига для теста. */
    private function makeConfigProvider(MaxOrderNotificationConfig $config): MaxOrderNotificationConfigProviderInterface
    {
        $provider = $this->createMock(MaxOrderNotificationConfigProviderInterface::class);
        $provider->method('config')->willReturn($config);

        return $provider;
    }

    /** Создаёт тестовый заказ. */
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
            deliveryDate: null,
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

    /** Создаёт тестового пользователя MAX для текста уведомления. */
    private function makeMaxUser(): MaxUserDisplayDto
    {
        return new MaxUserDisplayDto(
            maxUserId: 1002,
            firstName: 'Иван',
            username: 'ivan',
        );
    }
}
