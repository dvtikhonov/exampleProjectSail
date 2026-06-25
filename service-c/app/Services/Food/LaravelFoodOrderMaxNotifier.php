<?php

declare(strict_types=1);

namespace App\Services\Food;

use App\Contracts\Food\FoodOrderMaxNotifierInterface;
use App\Contracts\Max\MaxOrderNotificationConfigProviderInterface;
use App\DTO\Food\OrderDto;
use App\Models\MaxUser;
use App\Support\MaxOpenAppTargetResolver;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Support\Facades\Log;
use Shared\MaxMessenger\Contracts\MaxMessengerClientInterface;
use Shared\MaxMessenger\DTO\MaxInlineKeyboardButtonDto;
use Shared\MaxMessenger\DTO\MaxInlineKeyboardMessageDto;
use Shared\MaxMessenger\DTO\MaxMessageDto;
use Shared\MaxMessenger\Exceptions\MaxMessengerException;
use Throwable;

/**
 * Отправка уведомлений о новом заказе еды в чаты и пользователям MAX.
 */
class LaravelFoodOrderMaxNotifier implements FoodOrderMaxNotifierInterface
{
    public function __construct(
        private readonly MaxMessengerClientInterface $client,
        private readonly MaxOrderNotificationConfigProviderInterface $configProvider,
        private readonly FoodOrderMaxMessageBuilder $messageBuilder,
        private readonly MaxOpenAppTargetResolver $openAppTargetResolver,
        private readonly Repository $config,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function notify(OrderDto $order, MaxUser $maxUser): void
    {
        $config = $this->configProvider->config();

        if ($config->chatIds === [] && $config->userIds === []) {
            Log::channel('messMax')->warning('MAX order notification skipped: recipients are not configured', [
                'order_id' => $order->id,
            ]);

            return;
        }

        $text = $this->messageBuilder->build($order, $maxUser, $config->maxTextLength);
        $buttonRows = $this->buildOpenAppButtonRows();

        foreach ($config->chatIds as $chatId) {
            $this->trySendNotification($text, $buttonRows, orderId: $order->id, chatId: $chatId);
        }

        foreach ($config->userIds as $userId) {
            $this->trySendNotification($text, $buttonRows, orderId: $order->id, userId: $userId);
        }
    }

    /**
     * @return array<int, array<int, MaxInlineKeyboardButtonDto>>
     */
    private function buildOpenAppButtonRows(): array
    {
        $webAppUrl = $this->openAppTargetResolver->resolveWebApp();

        if ($webAppUrl === null) {
            return [];
        }

        return [
            [
                new MaxInlineKeyboardButtonDto(
                    text: (string) $this->config->get('max.ui_stand.mini_app_button_text', 'Заказ еды'),
                    type: 'open_app',
                    webApp: $webAppUrl,
                    contactId: $this->openAppTargetResolver->resolveContactId(),
                ),
            ],
        ];
    }

    /**
     * @param  array<int, array<int, MaxInlineKeyboardButtonDto>>  $buttonRows
     */
    private function trySendNotification(
        string $text,
        array $buttonRows,
        int $orderId,
        ?int $chatId = null,
        ?int $userId = null,
    ): void {
        try {
            if ($buttonRows !== []) {
                $this->client->sendInlineKeyboardMessage(new MaxInlineKeyboardMessageDto(
                    text: $text,
                    buttonRows: $buttonRows,
                    chatId: $chatId,
                    userId: $userId,
                ));

                return;
            }

            $this->client->sendMessage(new MaxMessageDto(
                text: $text,
                chatId: $chatId,
                userId: $userId,
            ));
        } catch (MaxMessengerException $exception) {
            Log::channel('messMax')->warning('MAX order notification send failed', [
                'order_id' => $orderId,
                'chat_id' => $chatId,
                'user_id' => $userId,
                'error' => $exception->userMessage(),
            ]);
        } catch (Throwable $exception) {
            Log::channel('messMax')->warning('MAX order notification send failed', [
                'order_id' => $orderId,
                'chat_id' => $chatId,
                'user_id' => $userId,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
