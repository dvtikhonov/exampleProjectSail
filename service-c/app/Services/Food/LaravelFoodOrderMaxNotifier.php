<?php

declare(strict_types=1);

namespace App\Services\Food;

use App\Contracts\Food\FoodOrderMaxNotifierInterface;
use App\Contracts\Max\MaxOrderNotificationConfigProviderInterface;
use App\DTO\Food\OrderDto;
use App\Models\MaxUser;
use Illuminate\Support\Facades\Log;
use Shared\MaxMessenger\Contracts\MaxMessengerClientInterface;
use Shared\MaxMessenger\DTO\MaxMessageDto;
use Shared\MaxMessenger\Exceptions\MaxMessengerException;
use Throwable;

class LaravelFoodOrderMaxNotifier implements FoodOrderMaxNotifierInterface
{
    public function __construct(
        private readonly MaxMessengerClientInterface $client,
        private readonly MaxOrderNotificationConfigProviderInterface $configProvider,
        private readonly FoodOrderMaxMessageBuilder $messageBuilder,
    ) {}

    public function notify(OrderDto $order, MaxUser $maxUser): void
    {
        $config = $this->configProvider->config();
        $text = $this->messageBuilder->build($order, $maxUser, $config->maxTextLength);

        foreach ($config->chatIds as $chatId) {
            $this->trySendMessage($text, orderId: $order->id, chatId: $chatId);
        }

        foreach ($config->userIds as $userId) {
            $this->trySendMessage($text, orderId: $order->id, userId: $userId);
        }
    }

    private function trySendMessage(
        string $text,
        int $orderId,
        ?int $chatId = null,
        ?int $userId = null,
    ): void {
        try {
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
