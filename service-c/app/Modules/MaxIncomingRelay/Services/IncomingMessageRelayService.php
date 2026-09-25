<?php

declare(strict_types=1);

namespace App\Modules\MaxIncomingRelay\Services;

use App\Contracts\Max\MaxMessengerNotificationSenderInterface;
use App\Contracts\Max\MaxUiStandRecipientResolverInterface;
use App\Modules\MaxIncomingRelay\Contracts\CustomerLastOrderRepositoryInterface;
use App\Modules\MaxIncomingRelay\Contracts\IncomingMessageRelayServiceInterface;
use App\Modules\MaxIncomingRelay\DTO\IncomingBotMessageDto;
use Psr\Log\LoggerInterface;

/**
 * Логирование и рассылка входящего сообщения боту в чаты Home_chat.
 */
final class IncomingMessageRelayService implements IncomingMessageRelayServiceInterface
{
    public function __construct(
        private readonly CustomerLastOrderRepositoryInterface $lastOrderRepository,
        private readonly IncomingMessageNotificationBuilder $notificationBuilder,
        private readonly MaxUiStandRecipientResolverInterface $recipientResolver,
        private readonly MaxMessengerNotificationSenderInterface $notificationSender,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * {@inheritdoc}
     */
    public function relay(IncomingBotMessageDto $message): void
    {
        $lastOrder = $this->lastOrderRepository->findLatestByMaxUserId($message->userId);
        $text = $this->notificationBuilder->build($message, $lastOrder);
        $chatIds = $this->recipientResolver->configuredChatIds();

        $this->logger->info('MAX incoming message relay', [
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
}
