<?php

declare(strict_types=1);

namespace App\Services\Food;

use App\Contracts\Food\FoodOrderAdminRepositoryInterface;
use App\Contracts\Food\OrderChatNotifierInterface;
use App\DTO\Food\OrderMessageDto;
use App\Enums\Food\OrderMessageAuthorType;
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
 * Отправка push-уведомлений MAX о новых сообщениях в чате заказа.
 */
class LaravelOrderChatNotifier implements OrderChatNotifierInterface
{
    public function __construct(
        private readonly MaxMessengerClientInterface $client,
        private readonly FoodOrderMaxMessageBuilder $messageBuilder,
        private readonly FoodOrderAdminRepositoryInterface $foodOrderAdminRepository,
        private readonly MaxOpenAppTargetResolver $openAppTargetResolver,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function notify(FoodOrder $order, OrderMessageDto $message): void
    {
        $text = $this->messageBuilder->buildOrderChatNotification($order, $message);
        $buttonRows = $this->buildOpenAppButtonRows($order->id);
        $recipientUserIds = $this->resolveRecipientUserIds($order, $message->authorType);

        if ($recipientUserIds === []) {
            Log::channel('messMax')->warning('MAX order chat notification skipped: no recipients', [
                'order_id' => $order->id,
                'message_id' => $message->id,
                'author_type' => $message->authorType->value,
            ]);

            return;
        }

        foreach ($recipientUserIds as $userId) {
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
     * @return list<int>
     */
    private function resolveRecipientUserIds(
        FoodOrder $order,
        OrderMessageAuthorType $authorType,
    ): array {
        if ($authorType === OrderMessageAuthorType::Customer) {
            return $this->foodOrderAdminRepository->listActiveAdminMaxUserIds();
        }

        return [$order->max_user_id];
    }

    /**
     * @return array<int, array<int, MaxInlineKeyboardButtonDto>>
     */
    private function buildOpenAppButtonRows(int $orderId): array
    {
        $webAppUrl = $this->messageBuilder->buildOrderChatOpenAppUrl(
            orderId: $orderId,
            baseWebAppUrl: $this->openAppTargetResolver->resolveWebApp(),
        );

        if ($webAppUrl === null) {
            return [];
        }

        return [
            [
                new MaxInlineKeyboardButtonDto(
                    text: 'Открыть в приложении',
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
        int $messageId,
        int $userId,
    ): void {
        try {
            if ($buttonRows !== []) {
                $this->client->sendInlineKeyboardMessage(new MaxInlineKeyboardMessageDto(
                    text: $text,
                    buttonRows: $buttonRows,
                    userId: $userId,
                ));

                return;
            }

            $this->client->sendMessage(new MaxMessageDto(
                text: $text,
                userId: $userId,
            ));
        } catch (MaxMessengerException $exception) {
            Log::channel('messMax')->warning('MAX order chat notification send failed', [
                'order_id' => $orderId,
                'message_id' => $messageId,
                'max_user_id' => $userId,
                'error' => $exception->userMessage(),
            ]);
        } catch (Throwable $exception) {
            Log::channel('messMax')->warning('MAX order chat notification send failed', [
                'order_id' => $orderId,
                'message_id' => $messageId,
                'max_user_id' => $userId,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
