<?php

declare(strict_types=1);

namespace App\Services\Max\UiStand;

use App\Contracts\Max\MaxMessengerNotificationSenderInterface;
use App\Contracts\Max\MaxUiStandGreetingSenderInterface;
use App\Contracts\Max\MaxUiStandRecipientResolverInterface;
use App\Contracts\Shared\ApplicationConfigInterface;
use App\Infrastructure\Laravel\MaxOpenAppButtonFactory;
use RuntimeException;
use Shared\MaxMessenger\DTO\MaxInlineKeyboardButtonDto;

/**
 * Отправка приветственного сообщения стенда MAX с кнопкой mini-app.
 */
class MaxUiStandGreetingSender implements MaxUiStandGreetingSenderInterface
{
    public function __construct(
        private readonly ApplicationConfigInterface $config,
        private readonly MaxOpenAppButtonFactory $openAppButtonFactory,
        private readonly MaxUiStandRecipientResolverInterface $recipientResolver,
        private readonly MaxMessengerNotificationSenderInterface $notificationSender,
    ) {}

    /**
     * Отправляет приветствие всем получателям из конфигурации.
     *
     * @throws RuntimeException
     */
    public function send(): void
    {
        $chatIds = $this->recipientResolver->configuredChatIds();
        $userIds = $this->recipientResolver->configuredUserIds();

        if ($chatIds === [] && $userIds === []) {
            throw new RuntimeException('MAX UI stand recipients are not configured.');
        }

        $this->sendToRecipients($chatIds, $userIds);
    }

    /**
     * Отправляет приветствие одному пользователю MAX.
     */
    public function sendToUser(int $userId): void
    {
        $this->sendToRecipients([], [$userId]);
    }

    /**
     * Отправляет приветствие UI-стенда получателям.
     *
     * @param  list<int>  $chatIds
     * @param  list<int>  $userIds
     */
    private function sendToRecipients(array $chatIds, array $userIds): void
    {
        $buttonRows = $this->buildButtonRows();
        $text = (string) $this->config->get(
            'max.ui_stand.greeting_text',
            'Привет!. Нужно нажать на кнопку "Заказ еды" или "Открыть".',
        );

        $successCount = 0;
        $failureCount = 0;

        foreach ($chatIds as $chatId) {
            if ($this->notificationSender->send(
                text: $text,
                chatId: $chatId,
                buttonRows: $buttonRows,
                failureLogMessage: 'MAX greeting send failed',
                logContext: [
                    'chat_id' => $chatId,
                    'user_id' => null,
                ],
            )) {
                $successCount++;
            } else {
                $failureCount++;
            }
        }

        foreach ($userIds as $userId) {
            if ($this->notificationSender->send(
                text: $text,
                userId: $userId,
                buttonRows: $buttonRows,
                failureLogMessage: 'MAX greeting send failed',
                logContext: [
                    'chat_id' => null,
                    'user_id' => $userId,
                ],
            )) {
                $successCount++;
            } else {
                $failureCount++;
            }
        }

        if ($successCount === 0 && $failureCount > 0) {
            throw new RuntimeException('Не удалось отправить приветствие ни одному получателю MAX.');
        }
    }

    /**
     * Строит ряды кнопок приветственного сообщения (только mini-app).
     *
     * @return array<int, array<int, MaxInlineKeyboardButtonDto>>
     */
    private function buildButtonRows(): array
    {
        return $this->openAppButtonFactory->buildGenericMiniAppButtonRows();
    }
}
