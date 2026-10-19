<?php

declare(strict_types=1);

namespace App\Infrastructure\Laravel;

use App\Contracts\Food\Chat\FoodOrderChatMaxMessageBuilderInterface;
use App\Contracts\Food\Review\OrderCustomerNotifyRecipientResolverInterface;
use App\Contracts\Max\MaxMessengerNotificationSenderInterface;
use App\Contracts\Max\MaxUserIdentityRepositoryInterface;
use App\Contracts\Telegram\TelegramMessengerNotificationSenderInterface;
use App\DTO\Food\Order\FoodOrderRecord;
use App\DTO\Max\MessengerUserIdentity;
use App\Enums\MessengerChannel;
use Shared\MaxMessenger\DTO\MaxInlineKeyboardButtonDto;

/**
 * Channel-aware collaborator: клиентские уведомления о заказе в MAX или Telegram.
 */
final class FoodOrderCustomerMessengerDispatchHelper
{
    public function __construct(
        private readonly FoodOrderChatMaxMessageBuilderInterface $chatMessageBuilder,
        private readonly MaxOpenAppButtonFactory $maxOpenAppButtonFactory,
        private readonly TelegramWebAppButtonFactory $telegramWebAppButtonFactory,
        private readonly OrderCustomerNotifyRecipientResolverInterface $recipientResolver,
        private readonly MaxUserIdentityRepositoryInterface $identityRepository,
        private readonly MaxMessengerNotificationSenderInterface $maxNotificationSender,
        private readonly TelegramMessengerNotificationSenderInterface $telegramNotificationSender,
    ) {}

    /**
     * Пытается отправить уведомление получателям клиентского канала заказа.
     *
     * @param  array<string, mixed>  $extraLogContext
     */
    public function trySendMessage(
        string $text,
        FoodOrderRecord $order,
        bool $withOpenAppButton = false,
        ?string $maxFailureLogMessage = null,
        ?string $telegramFailureLogMessage = null,
        array $extraLogContext = [],
    ): void {
        $recipientUserIds = $this->recipientResolver->resolveMaxUserIds($order);

        foreach ($recipientUserIds as $userId) {
            $this->trySendToUser(
                text: $text,
                order: $order,
                maxUserId: $userId,
                withOpenAppButton: $withOpenAppButton,
                maxFailureLogMessage: $maxFailureLogMessage,
                telegramFailureLogMessage: $telegramFailureLogMessage,
                extraLogContext: $extraLogContext,
            );
        }
    }

    /**
     * Пытается отправить одно уведомление в MAX-чат (UI Stand / fallback).
     */
    public function trySendToChat(string $text, FoodOrderRecord $order, int $chatId): bool
    {
        return $this->maxNotificationSender->send(
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
     * Пытается отправить одно уведомление конкретному получателю (по каналу messenger).
     *
     * @param  array<string, mixed>  $extraLogContext
     */
    public function trySendToUser(
        string $text,
        FoodOrderRecord $order,
        int $maxUserId,
        bool $withOpenAppButton = false,
        ?string $maxFailureLogMessage = null,
        ?string $telegramFailureLogMessage = null,
        array $extraLogContext = [],
    ): bool {
        $identity = $this->resolveIdentity($maxUserId);

        if ($identity->messenger === MessengerChannel::Telegram) {
            $buttonRows = $withOpenAppButton
                ? $this->telegramWebAppButtonFactory->buildOrderChatButtonRows(
                    $order->id,
                    $this->chatMessageBuilder->buildOrderChatStartParam($order->id),
                )
                : [];

            return $this->telegramNotificationSender->send(
                text: $text,
                chatId: $identity->externalUserId,
                buttonRows: $buttonRows,
                failureLogMessage: $telegramFailureLogMessage
                    ?? 'Telegram customer order notification send failed',
                logContext: [
                    ...$extraLogContext,
                    'order_id' => $order->id,
                    'max_user_id' => $identity->maxUserId,
                    'messenger' => MessengerChannel::Telegram->value,
                    'external_user_id' => $identity->externalUserId,
                ],
            );
        }

        /** @var array<int, array<int, MaxInlineKeyboardButtonDto>> $buttonRows */
        $buttonRows = $withOpenAppButton
            ? $this->maxOpenAppButtonFactory->buildOrderChatButtonRows(
                $order->id,
                $this->chatMessageBuilder->buildOrderChatStartParam($order->id),
            )
            : [];

        return $this->maxNotificationSender->send(
            text: $text,
            userId: $identity->externalUserId,
            buttonRows: $buttonRows,
            failureLogMessage: $maxFailureLogMessage
                ?? 'MAX customer order notification send failed',
            logContext: [
                ...$extraLogContext,
                'order_id' => $order->id,
                'max_user_id' => $identity->maxUserId,
                'messenger' => MessengerChannel::Max->value,
            ],
        );
    }

    /**
     * Канал + external id; при отсутствии строки в max_users — fallback на MAX / PK.
     */
    private function resolveIdentity(int $maxUserId): MessengerUserIdentity
    {
        $identity = $this->identityRepository->resolveMessengerIdentity($maxUserId);

        if ($identity !== null) {
            return $identity;
        }

        return new MessengerUserIdentity(
            maxUserId: $maxUserId,
            messenger: MessengerChannel::Max,
            externalUserId: $maxUserId,
        );
    }
}
