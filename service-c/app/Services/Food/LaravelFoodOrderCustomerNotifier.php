<?php

declare(strict_types=1);

namespace App\Services\Food;

use App\Contracts\Food\FoodOrderCustomerNotifierInterface;
use App\Enums\Food\OrderRejectionScope;
use App\Models\FoodOrder;
use App\Support\MaxOpenAppTargetResolver;
use Illuminate\Support\Facades\Log;
use Shared\MaxMessenger\Contracts\MaxMessengerClientInterface;
use Shared\MaxMessenger\DTO\MaxInlineKeyboardButtonDto;
use Shared\MaxMessenger\DTO\MaxInlineKeyboardMessageDto;
use Shared\MaxMessenger\DTO\MaxMessageDto;
use Shared\MaxMessenger\Exceptions\MaxMessengerException;
use Throwable;

/**
 * Отправка уведомлений клиенту о статусе заказа через MAX.
 */
class LaravelFoodOrderCustomerNotifier implements FoodOrderCustomerNotifierInterface
{
    public function __construct(
        private readonly MaxMessengerClientInterface $client,
        private readonly FoodOrderMaxMessageBuilder $messageBuilder,
        private readonly MaxOpenAppTargetResolver $openAppTargetResolver,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function notifySubmitted(FoodOrder $order): void
    {
        $text = $this->messageBuilder->buildCustomerSubmitted($order);
        $buttonRows = $this->buildOpenAppButtonRows($order->id);

        $this->trySendMessage($text, $order, $buttonRows);
    }

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

    /**
     * {@inheritDoc}
     */
    public function notifyCompositionChanged(FoodOrder $order): void
    {
        $text = $this->messageBuilder->buildCustomerCompositionChanged($order);
        $buttonRows = $this->buildOpenAppButtonRows($order->id);

        $this->trySendMessage($text, $order, $buttonRows);
    }

    /**
     * Строит ряды кнопок открытия mini-app для уведомления о заказе.
     *
     * web_app — базовый URL/username бота; payload — start_param order_{id}_chat.
     *
     * @return array<int, array<int, MaxInlineKeyboardButtonDto>>
     */
    private function buildOpenAppButtonRows(int $orderId): array
    {
        $webAppUrl = $this->openAppTargetResolver->resolveWebApp();

        if ($webAppUrl === null) {
            return [];
        }

        return [
            [
                new MaxInlineKeyboardButtonDto(
                    text: sprintf('Открыть заказ №%d', $orderId),
                    type: 'open_app',
                    payload: $this->messageBuilder->buildOrderChatStartParam($orderId),
                    webApp: $webAppUrl,
                    contactId: $this->openAppTargetResolver->resolveContactId(),
                ),
            ],
        ];
    }

    /**
     * Пытается отправить уведомление клиенту о заказе.
     *
     * @param  array<int, array<int, MaxInlineKeyboardButtonDto>>  $buttonRows
     */
    private function trySendMessage(string $text, FoodOrder $order, array $buttonRows = []): void
    {
        try {
            if ($buttonRows !== []) {
                $this->client->sendInlineKeyboardMessage(new MaxInlineKeyboardMessageDto(
                    text: $text,
                    buttonRows: $buttonRows,
                    userId: $order->max_user_id,
                ));

                return;
            }

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
