<?php

declare(strict_types=1);

namespace App\Services\Food;

use App\Contracts\Food\OrderChatNotifierInterface;
use App\DTO\Food\OrderMessageDto;
use App\Enums\Food\OrderMessageAuthorType;
use App\Models\FoodOrder;
use App\Support\MaxOpenAppTargetResolver;
use App\Support\MaxUiStandRecipientResolver;
use Illuminate\Support\Facades\Log;
use Shared\MaxMessenger\Contracts\MaxMessengerClientInterface;
use Shared\MaxMessenger\DTO\MaxInlineKeyboardButtonDto;
use Shared\MaxMessenger\DTO\MaxInlineKeyboardMessageDto;
use Shared\MaxMessenger\DTO\MaxMessageDto;
use Shared\MaxMessenger\Exceptions\MaxMessengerException;
use Throwable;

/**
 * Отправка push-уведомлений MAX о новых сообщениях в чате заказа.
 *
 * Клиенту — короткое уведомление (без своего же сообщения).
 * В MAX_UI_STAND_* — уведомление с текстом сообщения.
 */
class LaravelOrderChatNotifier implements OrderChatNotifierInterface
{
    public function __construct(
        private readonly MaxMessengerClientInterface $client,
        private readonly FoodOrderMaxMessageBuilder $messageBuilder,
        private readonly MaxUiStandRecipientResolver $uiStandRecipientResolver,
        private readonly MaxOpenAppTargetResolver $openAppTargetResolver,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function notify(FoodOrder $order, OrderMessageDto $message): void
    {
        $this->notifyUiStand($order, $message);

        if ($message->authorType === OrderMessageAuthorType::Admin) {
            $this->notifyCustomer($order, $message);
        }
    }

    /**
     * Уведомляет клиента о сообщении админа (без текста сообщения).
     */
    private function notifyCustomer(FoodOrder $order, OrderMessageDto $message): void
    {
        $text = $this->messageBuilder->buildOrderChatCustomerNotification($order);
        $buttonRows = $this->buildOpenAppButtonRows($order->id);

        $this->trySendNotification(
            text: $text,
            buttonRows: $buttonRows,
            orderId: $order->id,
            messageId: $message->id,
            userId: $order->max_user_id,
        );
    }

    /**
     * Уведомляет получателей UI Stand (MAX_UI_STAND_CHAT_IDS / USER_IDS).
     */
    private function notifyUiStand(FoodOrder $order, OrderMessageDto $message): void
    {
        $chatIds = $this->uiStandRecipientResolver->chatIds();
        $userIds = $this->uiStandRecipientResolver->userIds();

        if ($chatIds === [] && $userIds === []) {
            Log::channel('messMax')->warning('MAX order chat notification skipped: UI Stand recipients are not configured', [
                'order_id' => $order->id,
                'message_id' => $message->id,
                'author_type' => $message->authorType->value,
            ]);

            return;
        }

        $text = $this->messageBuilder->buildOrderChatUiStandNotification($order, $message);
        $buttonRows = $this->buildOpenAppButtonRows($order->id);

        foreach ($chatIds as $chatId) {
            $this->trySendNotification(
                text: $text,
                buttonRows: $buttonRows,
                orderId: $order->id,
                messageId: $message->id,
                chatId: $chatId,
            );
        }

        foreach ($userIds as $userId) {
            $this->trySendNotification(
                text: $text,
                buttonRows: $buttonRows,
                orderId: $order->id,
                messageId: $message->id,
                userId: $userId,
            );
        }
    }

    /**
     * Строит ряды кнопок открытия mini-app для уведомления.
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
     * Пытается отправить уведомление о сообщении в чате заказа.
     *
     * @param  array<int, array<int, MaxInlineKeyboardButtonDto>>  $buttonRows
     */
    private function trySendNotification(
        string $text,
        array $buttonRows,
        int $orderId,
        int $messageId,
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
            Log::channel('messMax')->warning('MAX order chat notification send failed', [
                'order_id' => $orderId,
                'message_id' => $messageId,
                'chat_id' => $chatId,
                'max_user_id' => $userId,
                'error' => $exception->userMessage(),
            ]);
        } catch (Throwable $exception) {
            Log::channel('messMax')->warning('MAX order chat notification send failed', [
                'order_id' => $orderId,
                'message_id' => $messageId,
                'chat_id' => $chatId,
                'max_user_id' => $userId,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
