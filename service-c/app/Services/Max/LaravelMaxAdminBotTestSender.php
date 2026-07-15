<?php

declare(strict_types=1);

namespace App\Services\Max;

use App\Contracts\Max\MaxAdminBotTestSenderInterface;
use App\Contracts\Max\MaxOrderNotificationConfigProviderInterface;
use App\DTO\Max\MaxAdminBotTestSendResultDto;
use App\Exceptions\Food\FoodDomainException;
use App\Support\MaxUiStandRecipientResolver;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Support\Facades\Log;
use Shared\MaxMessenger\Contracts\MaxMessengerClientInterface;
use Shared\MaxMessenger\DTO\MaxMessageDto;
use Shared\MaxMessenger\Exceptions\MaxMessengerAuthException;
use Shared\MaxMessenger\Exceptions\MaxMessengerException;
use Throwable;

/**
 * Отправка тестового сообщения «Тест БОТ» получателям уведомлений о заказах (MAX_REPORT_*).
 */
class LaravelMaxAdminBotTestSender implements MaxAdminBotTestSenderInterface
{
    public const TEST_MESSAGE_TEXT = 'Тест БОТ';

    public const UI_STAND_TEST_MESSAGE_TEXT = 'тест бот 2';

    public function __construct(
        private readonly MaxMessengerClientInterface $client,
        private readonly MaxOrderNotificationConfigProviderInterface $configProvider,
        private readonly Repository $config,
        private readonly MaxUiStandRecipientResolver $uiStandRecipientResolver,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function sendTestMessage(): MaxAdminBotTestSendResultDto
    {
        $this->assertBotConfigured();

        $notificationConfig = $this->configProvider->config();

        if ($notificationConfig->chatIds === [] && $notificationConfig->userIds === []) {
            throw new FoodDomainException(
                'Получатели не настроены. Укажите MAX_REPORT_CHAT_IDS или MAX_REPORT_USER_IDS в .env.',
                503,
            );
        }

        return $this->sendToRecipients(
            chatIds: $notificationConfig->chatIds,
            userIds: $notificationConfig->userIds,
            text: self::TEST_MESSAGE_TEXT,
        );
    }

    /**
     * {@inheritDoc}
     */
    public function sendUiStandTestMessage(): MaxAdminBotTestSendResultDto
    {
        $this->assertBotConfigured();

        $chatIds = $this->uiStandRecipientResolver->chatIds();
        $userIds = $this->uiStandRecipientResolver->userIds();

        if ($chatIds === [] && $userIds === []) {
            throw new FoodDomainException(
                'Получатели не настроены. Укажите MAX_UI_STAND_CHAT_IDS / MAX_UI_STAND_USER_IDS в .env '
                .'или нажмите кнопку «да»/«нет» в чате MAX, чтобы бот запомнил чат.',
                503,
            );
        }

        return $this->sendToRecipients(
            chatIds: $chatIds,
            userIds: $userIds,
            text: self::UI_STAND_TEST_MESSAGE_TEXT,
            recipientScope: 'ui_stand',
        );
    }

    /**
     * Отправляет тестовое сообщение бота списку получателей.
     *
     * @param  int[]  $chatIds
     * @param  int[]  $userIds
     */
    private function sendToRecipients(
        array $chatIds,
        array $userIds,
        string $text,
        string $recipientScope = 'order_notifications',
    ): MaxAdminBotTestSendResultDto {
        $sentCount = 0;
        $failureMessages = [];

        foreach ($chatIds as $chatId) {
            $errorMessage = $this->trySendMessage(text: $text, chatId: $chatId);

            if ($errorMessage === null) {
                $sentCount++;

                continue;
            }

            $failureMessages[] = $errorMessage;
        }

        foreach ($userIds as $userId) {
            $errorMessage = $this->trySendMessage(text: $text, userId: $userId);

            if ($errorMessage === null) {
                $sentCount++;

                continue;
            }

            $failureMessages[] = $errorMessage;
        }

        if ($sentCount === 0) {
            $details = $failureMessages !== []
                ? implode('; ', array_unique($failureMessages))
                : 'Не удалось отправить тестовое сообщение в MAX.';

            if ($recipientScope === 'ui_stand') {
                $details .= ' Проверьте MAX_UI_STAND_CHAT_IDS и MAX_UI_STAND_USER_IDS '
                    .'или снова нажмите кнопку в чате MAX перед тестом.';
            }

            throw new FoodDomainException($details, 502);
        }

        return new MaxAdminBotTestSendResultDto(sentCount: $sentCount);
    }

    /**
     * Пытается отправить сообщение одному получателю и возвращает успех.
     *
     * @return string|null Текст ошибки или null при успешной отправке
     */
    private function trySendMessage(string $text, ?int $chatId = null, ?int $userId = null): ?string
    {
        try {
            $this->client->sendMessage(new MaxMessageDto(
                text: $text,
                chatId: $chatId,
                userId: $userId,
            ));
        } catch (MaxMessengerAuthException) {
            return 'MAX_BOT_ACCESS_TOKEN не настроен или недействителен.';
        } catch (MaxMessengerException $exception) {
            $errorMessage = $this->formatRecipientError($exception->userMessage(), $chatId, $userId);

            Log::channel('messMax')->warning('MAX admin bot test message send failed', [
                'chat_id' => $chatId,
                'user_id' => $userId,
                'error' => $errorMessage,
            ]);

            return $errorMessage;
        } catch (Throwable $exception) {
            $errorMessage = $this->formatRecipientError($exception->getMessage(), $chatId, $userId);

            Log::channel('messMax')->warning('MAX admin bot test message send failed', [
                'chat_id' => $chatId,
                'user_id' => $userId,
                'error' => $errorMessage,
            ]);

            return $errorMessage;
        }

        return null;
    }

    /**
     * Форматирует текст ошибки отправки для получателя.
     */
    private function formatRecipientError(string $message, ?int $chatId, ?int $userId): string
    {
        if ($chatId !== null) {
            return "{$message} (chat_id: {$chatId})";
        }

        if ($userId !== null) {
            return "{$message} (user_id: {$userId})";
        }

        return $message;
    }

    /**
     * Проверяет, что бот MAX настроен через MAX_BOT_USERNAME и токен.
     */
    private function assertBotConfigured(): void
    {
        $botUsername = trim((string) $this->config->get('max.bot_username', ''));

        if ($botUsername === '') {
            throw new FoodDomainException(
                'MAX_BOT_USERNAME не настроен. Выполните max:bot:info и обновите .env.',
                503,
            );
        }

        $botAccessToken = trim((string) $this->config->get('max.bot_access_token', ''));

        if ($botAccessToken === '') {
            throw new FoodDomainException(
                'MAX_BOT_ACCESS_TOKEN не настроен. Выполните max:bot:info и обновите .env.',
                503,
            );
        }
    }
}
