<?php

declare(strict_types=1);

namespace App\Services\Telegram;

use App\Contracts\Food\Delivery\CustomerCategoryRepositoryInterface;
use App\Contracts\Max\MaxUserIdentityRepositoryInterface;
use App\Contracts\Shared\ApplicationConfigInterface;
use App\Contracts\Telegram\TelegramBotStartServiceInterface;
use App\Contracts\Telegram\TelegramMessengerNotificationSenderInterface;
use App\DTO\Telegram\TelegramInlineKeyboardButtonDto;
use Psr\Log\LoggerInterface;

/**
 * /start в Telegram: upsert в max_users + приветствие с кнопкой Mini App («экран пользователя»).
 */
final class TelegramBotStartService implements TelegramBotStartServiceInterface
{
    public function __construct(
        private readonly ApplicationConfigInterface $config,
        private readonly CustomerCategoryRepositoryInterface $customerCategoryRepository,
        private readonly MaxUserIdentityRepositoryInterface $maxUserRepository,
        private readonly TelegramMessengerNotificationSenderInterface $notificationSender,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function handleStart(
        int $telegramUserId,
        int $chatId,
        string $firstName,
        ?string $lastName,
        ?string $username,
        ?string $languageCode,
    ): void {
        $defaultCategoryId = $this->customerCategoryRepository->findOrCreateDefaultCategoryId();

        $user = $this->maxUserRepository->upsertFromTelegramBotProfile(
            telegramUserId: $telegramUserId,
            firstName: $firstName !== '' ? $firstName : 'User',
            lastName: $lastName,
            username: $username,
            languageCode: $languageCode,
            defaultCustomerCategoryId: $defaultCategoryId,
        );

        $this->logger->info('telegram_bot_started', [
            'telegram_user_id' => $telegramUserId,
            'max_user_id' => $user->maxUserId,
            'chat_id' => $chatId,
        ]);

        $text = (string) $this->config->get(
            'telegram.ui_stand.greeting_text',
            'Привет! Нужно нажать на кнопку "Заказ еды" или "Открыть".',
        );

        // #region agent log
        $expectedInternalId = 1_000_000_000_000_000 + $telegramUserId;
        try {
            $payload = [
                'sessionId' => '87a53d',
                'runId' => 'pre-fix',
                'hypothesisId' => 'H4',
                'location' => 'TelegramBotStartService.php',
                'message' => 'greeting send about to start',
                'data' => [
                    'telegram_user_id' => $telegramUserId,
                    'max_user_id' => $user->maxUserId,
                    'expected_internal_id' => $expectedInternalId,
                    'ids_match_offset' => $user->maxUserId === $expectedInternalId,
                    'chat_id' => $chatId,
                    'chat_id_equals_telegram_user' => $chatId === $telegramUserId,
                    'button_rows' => count($this->buildMiniAppButtonRows()),
                    'text_length' => strlen($text),
                ],
                'timestamp' => (int) round(microtime(true) * 1000),
            ];
            $line = json_encode($payload, JSON_UNESCAPED_UNICODE);
            if (is_string($line)) {
                @file_put_contents('/home/ddd/exampleProjectSail/.cursor/debug-87a53d.log', $line."\n", FILE_APPEND);
            }
            $this->logger->info('telegram_connect_probe', $payload);
        } catch (\Throwable) {
        }
        // #endregion

        $this->notificationSender->send(
            text: $text,
            chatId: $chatId,
            buttonRows: $this->buildMiniAppButtonRows(),
            failureLogMessage: 'Telegram /start greeting send failed',
            logContext: [
                'telegram_user_id' => $telegramUserId,
                'max_user_id' => $user->maxUserId,
            ],
        );
    }

    /**
     * @return array<int, array<int, TelegramInlineKeyboardButtonDto>>
     */
    private function buildMiniAppButtonRows(): array
    {
        $miniAppUrl = trim((string) $this->config->get('telegram.mini_app_url', ''));
        if ($miniAppUrl === '') {
            return [];
        }

        $buttonText = (string) $this->config->get(
            'telegram.ui_stand.mini_app_button_text',
            'Заказ еды',
        );

        return [
            [
                new TelegramInlineKeyboardButtonDto(
                    text: $buttonText,
                    webAppUrl: $miniAppUrl,
                ),
            ],
        ];
    }
}
