<?php

declare(strict_types=1);

namespace App\Infrastructure\Laravel;

use App\Contracts\Food\Chat\FoodOrderChatMaxMessageBuilderInterface;
use App\Contracts\Food\Review\OrderCustomerNotifyRecipientResolverInterface;
use App\Contracts\Max\MaxMessengerNotificationSenderInterface;
use App\DTO\Food\Order\FoodOrderRecord;
use Shared\MaxMessenger\DTO\MaxInlineKeyboardButtonDto;

/**
 * Внутренний collaborator: отправка клиентских MAX-уведомлений о заказе.
 */
final class FoodOrderCustomerMaxDispatchHelper
{
    public function __construct(
        private readonly FoodOrderChatMaxMessageBuilderInterface $chatMessageBuilder,
        private readonly MaxOpenAppButtonFactory $openAppButtonFactory,
        private readonly OrderCustomerNotifyRecipientResolverInterface $recipientResolver,
        private readonly MaxMessengerNotificationSenderInterface $notificationSender,
    ) {}

    /**
     * Строит ряды кнопок открытия mini-app для уведомления о заказе.
     *
     * @return array<int, array<int, MaxInlineKeyboardButtonDto>>
     */
    public function buildOpenAppButtonRows(int $orderId): array
    {
        return $this->openAppButtonFactory->buildOrderChatButtonRows(
            $orderId,
            $this->chatMessageBuilder->buildOrderChatStartParam($orderId),
        );
    }

    /**
     * Пытается отправить уведомление получателям клиентского канала заказа.
     *
     * @param  array<int, array<int, MaxInlineKeyboardButtonDto>>  $buttonRows
     */
    public function trySendMessage(string $text, FoodOrderRecord $order, array $buttonRows = []): void
    {
        $recipientUserIds = $this->recipientResolver->resolveMaxUserIds($order);

        foreach ($recipientUserIds as $userId) {
            $this->trySendToUser($text, $order, $userId, $buttonRows);
        }
    }

    /**
     * Пытается отправить одно уведомление в MAX-чат.
     */
    public function trySendToChat(string $text, FoodOrderRecord $order, int $chatId): bool
    {
        return $this->notificationSender->send(
            text: $text,
            chatId: $chatId,
            failureLogMessage: 'MAX customer order notification send failed',
            logContext: [
                'order_id' => $order->id,
                'chat_id' => $chatId,
            ],
        );
    }

    /**
     * Пытается отправить одно уведомление конкретному получателю.
     *
     * @param  array<int, array<int, MaxInlineKeyboardButtonDto>>  $buttonRows
     */
    public function trySendToUser(
        string $text,
        FoodOrderRecord $order,
        int $userId,
        array $buttonRows = [],
    ): bool {
        return $this->notificationSender->send(
            text: $text,
            userId: $userId,
            buttonRows: $buttonRows,
            failureLogMessage: 'MAX customer order notification send failed',
            logContext: [
                'order_id' => $order->id,
                'max_user_id' => $userId,
            ],
        );
    }
}
