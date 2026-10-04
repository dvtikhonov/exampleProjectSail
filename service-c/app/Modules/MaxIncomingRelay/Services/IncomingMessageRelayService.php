<?php

declare(strict_types=1);

namespace App\Modules\MaxIncomingRelay\Services;

use App\Contracts\Max\MaxMessengerNotificationSenderInterface;
use App\Contracts\Max\MaxUiStandRecipientResolverInterface;
use App\Contracts\Max\MaxUserIdentityRepositoryInterface;
use App\Modules\MaxIncomingRelay\Contracts\BotDmMessageRepositoryInterface;
use App\Modules\MaxIncomingRelay\Contracts\CustomerLastOrderRepositoryInterface;
use App\Modules\MaxIncomingRelay\Contracts\IncomingMessageRelayServiceInterface;
use App\Modules\MaxIncomingRelay\DTO\IncomingBotMessageDto;
use App\Modules\MaxIncomingRelay\Enums\BotDmAuthorType;
use Psr\Log\LoggerInterface;

/**
 * Логирование, сохранение истории лички (для known max_users) и рассылка в Home_chat.
 */
final class IncomingMessageRelayService implements IncomingMessageRelayServiceInterface
{
    public function __construct(
        private readonly CustomerLastOrderRepositoryInterface $lastOrderRepository,
        private readonly IncomingMessageNotificationBuilder $notificationBuilder,
        private readonly MaxUiStandRecipientResolverInterface $recipientResolver,
        private readonly MaxMessengerNotificationSenderInterface $notificationSender,
        private readonly BotDmMessageRepositoryInterface $botDmMessageRepository,
        private readonly MaxUserIdentityRepositoryInterface $maxUserIdentityRepository,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * {@inheritdoc}
     */
    public function relay(IncomingBotMessageDto $message): void
    {
        $this->persistCustomerMessageIfEligible($message);

        $lastOrder = $this->lastOrderRepository->findLatestByMaxUserId($message->userId);
        $text = $this->notificationBuilder->build($message, $lastOrder);
        $chatIds = $this->recipientResolver->configuredChatIds();

        $this->logger->warning('MAX incoming message relay', [
            'user_id' => $message->userId,
            'chat_id' => $message->chatId,
            'chat_ids' => $chatIds,
            'text' => $text,
        ]);

        if ($chatIds === []) {
            $this->logger->warning('MAX incoming message relay skipped: MAX_UI_STAND_CHAT_IDS is empty', [
                'user_id' => $message->userId,
                'chat_id' => $message->chatId,
            ]);

            return;
        }

        foreach ($chatIds as $chatId) {
            $this->notificationSender->send(
                text: $text,
                chatId: $chatId,
                failureLogMessage: 'MAX incoming message relay send failed',
                logContext: [
                    'user_id' => $message->userId,
                    'chat_id' => $chatId,
                ],
            );
        }
    }

    /**
     * Сохраняет входящее сообщение в историю лички, если отправитель есть в max_users и текст непустой.
     * Рассылка в Home_chat от этого не зависит.
     */
    private function persistCustomerMessageIfEligible(IncomingBotMessageDto $message): void
    {
        $body = trim($message->text);
        if ($body === '') {
            return;
        }

        if ($this->maxUserIdentityRepository->findByMaxUserId($message->userId) === null) {
            return;
        }

        $this->botDmMessageRepository->create(
            maxUserId: $message->userId,
            senderMaxUserId: $message->userId,
            authorType: BotDmAuthorType::Customer,
            body: $body,
            chatId: $message->chatId,
        );
    }
}
