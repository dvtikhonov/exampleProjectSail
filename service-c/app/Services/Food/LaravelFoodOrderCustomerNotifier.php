<?php

declare(strict_types=1);

namespace App\Services\Food;

use App\Contracts\Food\FoodOrderCustomerNotifierInterface;
use App\Contracts\Food\OrderCustomerNotifyRecipientResolverInterface;
use App\Enums\Food\OrderRejectionScope;
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
 * Отправка уведомлений клиенту о статусе заказа через MAX.
 */
class LaravelFoodOrderCustomerNotifier implements FoodOrderCustomerNotifierInterface
{
    public function __construct(
        private readonly MaxMessengerClientInterface $client,
        private readonly FoodOrderMaxMessageBuilder $messageBuilder,
        private readonly MaxOpenAppTargetResolver $openAppTargetResolver,
        private readonly OrderCustomerNotifyRecipientResolverInterface $recipientResolver,
        private readonly MaxUiStandRecipientResolver $uiStandRecipientResolver,
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

        $this->notifyManualOrderCreatorIfNeeded($order);
    }

    /**
     * Дополнительно уведомляет менеджера, оформившего ручной заказ, детальным составом.
     *
     * Сначала DM на created_by_max_user_id; при ошибке MAX (например демо-id → 404)
     * — fallback в получатели UI Stand (MAX_UI_STAND_*), куда уже приходят рабочие уведомления.
     */
    private function notifyManualOrderCreatorIfNeeded(FoodOrder $order): void
    {
        if (! $order->is_manual) {
            return;
        }

        $creatorId = $order->created_by_max_user_id;

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
    private function trySendManualCreatorToUiStand(string $text, FoodOrder $order): void
    {
        $chatIds = $this->uiStandRecipientResolver->chatIds();
        $userIds = $this->uiStandRecipientResolver->userIds();

        if ($chatIds === [] && $userIds === []) {
            Log::channel('messMax')->warning(
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
     * Пытается отправить уведомление получателям клиентского канала заказа.
     *
     * @param  array<int, array<int, MaxInlineKeyboardButtonDto>>  $buttonRows
     */
    private function trySendMessage(string $text, FoodOrder $order, array $buttonRows = []): void
    {
        $recipientUserIds = $this->recipientResolver->resolveMaxUserIds($order);

        foreach ($recipientUserIds as $userId) {
            $this->trySendToUser($text, $order, $userId, $buttonRows);
        }
    }

    /**
     * Пытается отправить одно уведомление в MAX-чат.
     */
    private function trySendToChat(string $text, FoodOrder $order, int $chatId): bool
    {
        try {
            $this->client->sendMessage(new MaxMessageDto(
                text: $text,
                chatId: $chatId,
            ));

            return true;
        } catch (MaxMessengerException $exception) {
            Log::channel('messMax')->warning('MAX customer order notification send failed', [
                'order_id' => $order->id,
                'chat_id' => $chatId,
                'error' => $exception->userMessage(),
            ]);

            return false;
        } catch (Throwable $exception) {
            Log::channel('messMax')->warning('MAX customer order notification send failed', [
                'order_id' => $order->id,
                'chat_id' => $chatId,
                'error' => $exception->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Пытается отправить одно уведомление конкретному получателю.
     *
     * @param  array<int, array<int, MaxInlineKeyboardButtonDto>>  $buttonRows
     */
    private function trySendToUser(
        string $text,
        FoodOrder $order,
        int $userId,
        array $buttonRows = [],
    ): bool {
        try {
            if ($buttonRows !== []) {
                $this->client->sendInlineKeyboardMessage(new MaxInlineKeyboardMessageDto(
                    text: $text,
                    buttonRows: $buttonRows,
                    userId: $userId,
                ));

                return true;
            }

            $this->client->sendMessage(new MaxMessageDto(
                text: $text,
                userId: $userId,
            ));

            return true;
        } catch (MaxMessengerException $exception) {
            Log::channel('messMax')->warning('MAX customer order notification send failed', [
                'order_id' => $order->id,
                'max_user_id' => $userId,
                'error' => $exception->userMessage(),
            ]);

            return false;
        } catch (Throwable $exception) {
            Log::channel('messMax')->warning('MAX customer order notification send failed', [
                'order_id' => $order->id,
                'max_user_id' => $userId,
                'error' => $exception->getMessage(),
            ]);

            return false;
        }
    }
}
