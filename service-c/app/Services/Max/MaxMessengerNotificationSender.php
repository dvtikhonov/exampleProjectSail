<?php

declare(strict_types=1);

namespace App\Services\Max;

use App\Contracts\Max\MaxMessengerNotificationSenderInterface;
use App\Contracts\Max\MaxUiStandRecipientResolverInterface;
use Psr\Log\LoggerInterface;
use Shared\MaxMessenger\Contracts\MaxMessengerClientInterface;
use Shared\MaxMessenger\DTO\MaxInlineKeyboardMessageDto;
use Shared\MaxMessenger\DTO\MaxMessageDto;
use Shared\MaxMessenger\Exceptions\MaxMessengerException;
use Throwable;

/**
 * Единая отправка MAX-уведомлений с обработкой ошибок и логированием.
 */
final class MaxMessengerNotificationSender implements MaxMessengerNotificationSenderInterface
{
    public function __construct(
        private readonly MaxMessengerClientInterface $client,
        private readonly MaxUiStandRecipientResolverInterface $uiStandRecipientResolver,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function send(
        string $text,
        ?int $chatId = null,
        ?int $userId = null,
        array $buttonRows = [],
        string $failureLogMessage = 'MAX notification send failed',
        array $logContext = [],
    ): bool {
        try {
            if ($buttonRows !== []) {
                $this->client->sendInlineKeyboardMessage(new MaxInlineKeyboardMessageDto(
                    text: $text,
                    buttonRows: $buttonRows,
                    chatId: $chatId,
                    userId: $userId,
                ));

                return true;
            }

            $this->client->sendMessage(new MaxMessageDto(
                text: $text,
                chatId: $chatId,
                userId: $userId,
            ));

            return true;
        } catch (MaxMessengerException $exception) {
            $this->logger->warning($failureLogMessage, [
                ...$logContext,
                'error' => $exception->userMessage(),
            ]);

            return false;
        } catch (Throwable $exception) {
            $this->logger->warning($failureLogMessage, [
                ...$logContext,
                'error' => $exception->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * {@inheritDoc}
     */
    public function broadcastToUiStand(
        string $text,
        array $buttonRows,
        array $logContext,
        string $failureLogMessage = 'MAX notification send failed',
    ): void {
        foreach ($this->uiStandRecipientResolver->chatIds() as $chatId) {
            $this->send(
                text: $text,
                chatId: $chatId,
                buttonRows: $buttonRows,
                failureLogMessage: $failureLogMessage,
                logContext: [
                    ...$logContext,
                    'chat_id' => $chatId,
                    'user_id' => null,
                ],
            );
        }

        foreach ($this->uiStandRecipientResolver->userIds() as $userId) {
            $this->send(
                text: $text,
                userId: $userId,
                buttonRows: $buttonRows,
                failureLogMessage: $failureLogMessage,
                logContext: [
                    ...$logContext,
                    'chat_id' => null,
                    'user_id' => $userId,
                ],
            );
        }
    }
}
