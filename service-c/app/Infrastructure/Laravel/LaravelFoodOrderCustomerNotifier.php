<?php

declare(strict_types=1);

namespace App\Infrastructure\Laravel;

use App\Contracts\Food\Review\FoodOrderCustomerNotifierInterface;
use App\Contracts\Food\Review\OrderCustomerNotifyRecipientResolverInterface;
use App\Contracts\Max\MaxMessengerNotificationSenderInterface;
use App\Contracts\Max\MaxUiStandRecipientResolverInterface;
use App\DTO\Food\Order\FoodOrderRecord;
use App\Enums\Food\Review\OrderRejectionScope;
use App\Services\Max\Food\FoodOrderMaxMessageBuilder;
use App\Support\Max\MaxOpenAppButtonFactory;
use Illuminate\Support\Facades\Log;
use Shared\MaxMessenger\DTO\MaxInlineKeyboardButtonDto;

/**
 * Отправка уведомлений клиенту о статусе заказа через MAX.
 */
class LaravelFoodOrderCustomerNotifier implements FoodOrderCustomerNotifierInterface
{
    public function __construct(
        private readonly FoodOrderMaxMessageBuilder $messageBuilder,
        private readonly MaxOpenAppButtonFactory $openAppButtonFactory,
        private readonly OrderCustomerNotifyRecipientResolverInterface $recipientResolver,
        private readonly MaxUiStandRecipientResolverInterface $uiStandRecipientResolver,
        private readonly MaxMessengerNotificationSenderInterface $notificationSender,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function notifySubmitted(FoodOrderRecord $order): void
    {
        $text = $this->messageBuilder->buildCustomerSubmitted($order);
        $buttonRows = $this->buildOpenAppButtonRows($order->id);

        $this->trySendMessage($text, $order, $buttonRows);
    }

    /**
     * {@inheritDoc}
     */
    public function notifyConfirmed(FoodOrderRecord $order): void
    {
        $text = $this->messageBuilder->buildCustomerConfirmed($order);

        $this->trySendMessage($text, $order);

        $this->notifyManualOrderCreatorConfirmed($order);
    }

    /**
     * {@inheritDoc}
     *
     * Сначала DM на created_by_max_user_id; при ошибке MAX (например демо-id → 404)
     * — fallback в получатели UI Stand (MAX_UI_STAND_*), куда уже приходят рабочие уведомления.
     */
    public function notifyManualOrderCreatorConfirmed(FoodOrderRecord $order): void
    {
        if (! $order->isManual) {
            return;
        }

        $creatorId = $order->createdByMaxUserId;

        if ($creatorId === null) {
            return;
        }

        $text = $this->messageBuilder->buildManualOrderCreatorConfirmed($order);

        $sent = $this->trySendToUser($text, $order, (int) $creatorId);

        if (! $sent) {
            $this->trySendManualCreatorToUiStand($text, $order);
        }
    }

    /**
     * Fallback: детальный состав ручного заказа в UI Stand (chat_id / user_id).
     */
    private function trySendManualCreatorToUiStand(string $text, FoodOrderRecord $order): void
    {
        $chatIds = $this->uiStandRecipientResolver->chatIds();
        $userIds = $this->uiStandRecipientResolver->userIds();

        if ($chatIds === [] && $userIds === []) {
            Log::channel('max_log')->warning(
                'MAX manual order creator notification fallback skipped: UI Stand recipients are not configured',
                ['order_id' => $order->id],
            );

            return;
        }

        foreach ($chatIds as $chatId) {
            $this->trySendToChat($text, $order, $chatId);
        }

        foreach ($userIds as $userId) {
            $this->trySendToUser($text, $order, $userId);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function notifyRejected(FoodOrderRecord $order, OrderRejectionScope $scope): void
    {
        $text = $this->messageBuilder->buildCustomerRejected($order, $scope);

        $this->trySendMessage($text, $order);
    }

    /**
     * {@inheritDoc}
     */
    public function notifyCompositionChanged(FoodOrderRecord $order): void
    {
        $text = $this->messageBuilder->buildCustomerCompositionChanged($order);
        $buttonRows = $this->buildOpenAppButtonRows($order->id);

        $this->trySendMessage($text, $order, $buttonRows);
    }

    /**
     * Строит ряды кнопок открытия mini-app для уведомления о заказе.
     *
     * @return array<int, array<int, MaxInlineKeyboardButtonDto>>
     */
    private function buildOpenAppButtonRows(int $orderId): array
    {
        return $this->openAppButtonFactory->buildOrderChatButtonRows(
            $orderId,
            $this->messageBuilder->buildOrderChatStartParam($orderId),
        );
    }

    /**
     * Пытается отправить уведомление получателям клиентского канала заказа.
     *
     * @param  array<int, array<int, MaxInlineKeyboardButtonDto>>  $buttonRows
     */
    private function trySendMessage(string $text, FoodOrderRecord $order, array $buttonRows = []): void
    {
        $recipientUserIds = $this->recipientResolver->resolveMaxUserIds($order);

        foreach ($recipientUserIds as $userId) {
            $this->trySendToUser($text, $order, $userId, $buttonRows);
        }
    }

    /**
     * Пытается отправить одно уведомление в MAX-чат.
     */
    private function trySendToChat(string $text, FoodOrderRecord $order, int $chatId): bool
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
    private function trySendToUser(
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
