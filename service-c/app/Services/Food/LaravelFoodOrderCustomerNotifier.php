<?php

declare(strict_types=1);

namespace App\Services\Food;

use App\Contracts\Food\FoodOrderCustomerNotifierInterface;
use App\Enums\Food\OrderRejectionScope;
use App\Models\FoodOrder;
use Illuminate\Support\Facades\Log;
use Shared\MaxMessenger\Contracts\MaxMessengerClientInterface;
use Shared\MaxMessenger\DTO\MaxMessageDto;
use Shared\MaxMessenger\Exceptions\MaxMessengerException;
use Throwable;

/**
 * Отправка уведомлений клиенту о результате проверки заказа через MAX.
 */
class LaravelFoodOrderCustomerNotifier implements FoodOrderCustomerNotifierInterface
{
    public function __construct(
        private readonly MaxMessengerClientInterface $client,
        private readonly FoodOrderMaxMessageBuilder $messageBuilder,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function notifyConfirmed(FoodOrder $order): void
    {
        $text = $this->messageBuilder->buildCustomerConfirmed($order);

        $this->trySendMessage($text, $order);
    }

    /**
     * {@inheritDoc}
     */
    public function notifyRejected(FoodOrder $order, OrderRejectionScope $scope): void
    {
        $text = $this->messageBuilder->buildCustomerRejected($order, $scope);

        $this->trySendMessage($text, $order);
    }

    private function trySendMessage(string $text, FoodOrder $order): void
    {
        try {
            $this->client->sendMessage(new MaxMessageDto(
                text: $text,
                userId: $order->max_user_id,
            ));
        } catch (MaxMessengerException $exception) {
            Log::channel('messMax')->warning('MAX customer order notification send failed', [
                'order_id' => $order->id,
                'max_user_id' => $order->max_user_id,
                'error' => $exception->userMessage(),
            ]);
        } catch (Throwable $exception) {
            Log::channel('messMax')->warning('MAX customer order notification send failed', [
                'order_id' => $order->id,
                'max_user_id' => $order->max_user_id,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
