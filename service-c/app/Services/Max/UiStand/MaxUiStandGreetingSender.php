<?php

namespace App\Services\Max\UiStand;

use App\Support\MaxOpenAppTargetResolver;
use App\Support\MaxUiStandRecipientResolver;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Shared\MaxMessenger\Contracts\MaxMessengerClientInterface;
use Shared\MaxMessenger\DTO\MaxInlineKeyboardButtonDto;
use Shared\MaxMessenger\DTO\MaxInlineKeyboardMessageDto;
use Shared\MaxMessenger\Exceptions\MaxMessengerException;
use Throwable;

/**
 * Отправка приветственного сообщения стенда MAX с inline-клавиатурой.
 */
class MaxUiStandGreetingSender
{
    public function __construct(
        private readonly MaxMessengerClientInterface $client,
        private readonly Repository $config,
        private readonly MaxOpenAppTargetResolver $openAppTargetResolver,
        private readonly MaxUiStandRecipientResolver $recipientResolver,
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
        $text = (string) $this->config->get('max.ui_stand.greeting_text', 'Привет! Выберите ответ:');

        $successCount = 0;
        $failureCount = 0;

        foreach ($chatIds as $chatId) {
            if ($this->trySendInlineKeyboard($text, $buttonRows, chatId: $chatId)) {
                $successCount++;
            } else {
                $failureCount++;
            }
        }

        foreach ($userIds as $userId) {
            if ($this->trySendInlineKeyboard($text, $buttonRows, userId: $userId)) {
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
     * Пытается отправить сообщение с inline-клавиатурой.
     *
     * @param  array<int, array<int, MaxInlineKeyboardButtonDto>>  $buttonRows
     */
    private function trySendInlineKeyboard(
        string $text,
        array $buttonRows,
        ?int $chatId = null,
        ?int $userId = null,
    ): bool {
        try {
            $this->client->sendInlineKeyboardMessage(new MaxInlineKeyboardMessageDto(
                text: $text,
                buttonRows: $buttonRows,
                chatId: $chatId,
                userId: $userId,
            ));

            return true;
        } catch (MaxMessengerException $exception) {
            Log::channel('messMax')->warning('MAX greeting send failed', [
                'chat_id' => $chatId,
                'user_id' => $userId,
                'error' => $exception->userMessage(),
            ]);

            return false;
        } catch (Throwable $exception) {
            Log::channel('messMax')->warning('MAX greeting send failed', [
                'chat_id' => $chatId,
                'user_id' => $userId,
                'error' => $exception->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Строит ряды кнопок приветственного сообщения.
     *
     * @return array<int, array<int, MaxInlineKeyboardButtonDto>>
     */
    private function buildButtonRows(): array
    {
        $rows = [];

        $openAppTarget = $this->openAppTargetResolver->resolveWebApp();
        if ($openAppTarget !== null) {
            $rows[] = [
                new MaxInlineKeyboardButtonDto(
                    text: (string) $this->config->get('max.ui_stand.mini_app_button_text', 'Заказ еды'),
                    type: 'open_app',
                    webApp: $openAppTarget,
                    contactId: $this->openAppTargetResolver->resolveContactId(),
                ),
            ];
        }

        $rows[] = [
            new MaxInlineKeyboardButtonDto(
                text: 'да',
                payload: (string) $this->config->get('max.ui_stand.button_yes_payload', 'yes'),
            ),
            new MaxInlineKeyboardButtonDto(
                text: 'нет',
                payload: (string) $this->config->get('max.ui_stand.button_no_payload', 'no'),
            ),
        ];

        return $rows;
    }
}
