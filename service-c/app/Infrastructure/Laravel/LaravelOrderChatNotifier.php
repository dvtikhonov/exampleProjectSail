<?php

declare(strict_types=1);

namespace App\Infrastructure\Laravel;

use App\Contracts\Food\Chat\OrderChatNotifierInterface;
use App\Contracts\Food\Review\OrderCustomerNotifyRecipientResolverInterface;
use App\Contracts\Max\MaxMessengerNotificationSenderInterface;
use App\DTO\Food\Chat\OrderMessageDto;
use App\DTO\Food\Order\FoodOrderRecord;
use App\Enums\Food\Chat\OrderMessageAuthorType;
use App\Services\Max\Food\FoodOrderMaxMessageBuilder;
use App\Support\Max\MaxOpenAppButtonFactory;
use App\Support\Max\MaxUiStandRecipientResolver;
use Illuminate\Support\Facades\Log;
use Shared\MaxMessenger\DTO\MaxInlineKeyboardButtonDto;

/**
 * Отправка push-уведомлений MAX о новых сообщениях в чате заказа.
 *
 * Клиенту — короткое уведомление (без своего же сообщения).
 * В MAX_UI_STAND_* — уведомление с текстом сообщения.
 */
class LaravelOrderChatNotifier implements OrderChatNotifierInterface
{
    public function __construct(
        private readonly FoodOrderMaxMessageBuilder $messageBuilder,
        private readonly MaxUiStandRecipientResolver $uiStandRecipientResolver,
        private readonly MaxOpenAppButtonFactory $openAppButtonFactory,
        private readonly OrderCustomerNotifyRecipientResolverInterface $customerRecipientResolver,
        private readonly MaxMessengerNotificationSenderInterface $notificationSender,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function notify(FoodOrderRecord $order, OrderMessageDto $message): void
    {
        $this->notifyUiStand($order, $message);

        if ($message->authorType === OrderMessageAuthorType::Admin) {
            $this->notifyCustomer($order, $message);
        }
    }

    /**
     * Уведомляет получателей клиентского канала о сообщении админа (без текста сообщения).
     */
    private function notifyCustomer(FoodOrderRecord $order, OrderMessageDto $message): void
    {
        $text = $this->messageBuilder->buildOrderChatCustomerNotification($order);
        $buttonRows = $this->buildOpenAppButtonRows($order->id);
        $recipientUserIds = $this->customerRecipientResolver->resolveMaxUserIds($order);

        foreach ($recipientUserIds as $userId) {
            $this->notificationSender->send(
                text: $text,
                userId: $userId,
                buttonRows: $buttonRows,
                failureLogMessage: 'MAX order chat notification send failed',
                logContext: [
                    'order_id' => $order->id,
                    'message_id' => $message->id,
                    'chat_id' => null,
                    'max_user_id' => $userId,
                ],
            );
        }
    }

    /**
     * Уведомляет получателей UI Stand (MAX_UI_STAND_CHAT_IDS / USER_IDS).
     */
    private function notifyUiStand(FoodOrderRecord $order, OrderMessageDto $message): void
    {
        $chatIds = $this->uiStandRecipientResolver->chatIds();
        $userIds = $this->uiStandRecipientResolver->userIds();

        if ($chatIds === [] && $userIds === []) {
            Log::channel('max_log')->warning('MAX order chat notification skipped: UI Stand recipients are not configured', [
                'order_id' => $order->id,
                'message_id' => $message->id,
                'author_type' => $message->authorType->value,
            ]);

            return;
        }

        $text = $this->messageBuilder->buildOrderChatUiStandNotification($order, $message);
        $buttonRows = $this->buildOpenAppButtonRows($order->id);

        foreach ($chatIds as $chatId) {
            $this->notificationSender->send(
                text: $text,
                chatId: $chatId,
                buttonRows: $buttonRows,
                failureLogMessage: 'MAX order chat notification send failed',
                logContext: [
                    'order_id' => $order->id,
                    'message_id' => $message->id,
                    'chat_id' => $chatId,
                    'max_user_id' => null,
                ],
            );
        }

        foreach ($userIds as $userId) {
            $this->notificationSender->send(
                text: $text,
                userId: $userId,
                buttonRows: $buttonRows,
                failureLogMessage: 'MAX order chat notification send failed',
                logContext: [
                    'order_id' => $order->id,
                    'message_id' => $message->id,
                    'chat_id' => null,
                    'max_user_id' => $userId,
                ],
            );
        }
    }

    /**
     * Строит ряды кнопок открытия mini-app для уведомления.
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
}
