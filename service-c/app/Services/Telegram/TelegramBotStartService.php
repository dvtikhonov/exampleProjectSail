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
            'Привет!. Нужно нажать на кнопку "Заказ еды" или "Открыть".',
        );

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
