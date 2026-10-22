<?php

declare(strict_types=1);

namespace App\Services\Telegram;

use App\Contracts\Max\MaxUserDeliveryRepositoryInterface;
use App\Contracts\Shared\ApplicationConfigInterface;
use App\Contracts\Telegram\TelegramMenuAvailabilityNotifierInterface;
use App\Contracts\Telegram\TelegramMessengerNotificationSenderInterface;
use App\Enums\MessengerChannel;
use App\Services\Food\Menu\MenuAvailabilityClientNotificationText;
use DateTimeInterface;
use Psr\Log\LoggerInterface;

/**
 * Уведомление в Telegram о доступности меню на дату «Блюда на».
 *
 * Получатели: TELEGRAM_REPORT_* и пользователи messenger=telegram с адресом доставки.
 */
class TelegramMenuAvailabilityNotifier implements TelegramMenuAvailabilityNotifierInterface
{
    public function __construct(
        private readonly ApplicationConfigInterface $config,
        private readonly MaxUserDeliveryRepositoryInterface $maxUserRepository,
        private readonly TelegramMessengerNotificationSenderInterface $notificationSender,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function notify(DateTimeInterface $menuDate): int
    {
        if (! $this->isBotConfigured()) {
            $this->logger->warning('Telegram menu availability notification skipped: bot is not configured');

            return 0;
        }

        $chatIds = $this->resolveRecipientChatIds();

        if ($chatIds === []) {
            $this->logger->warning('Telegram menu availability notification skipped: recipients are not configured');

            return 0;
        }

        $text = MenuAvailabilityClientNotificationText::forDate($menuDate);
        $sentCount = 0;

        foreach ($chatIds as $chatId) {
            if ($this->notificationSender->send(
                text: $text,
                chatId: $chatId,
                failureLogMessage: 'Telegram menu availability notification send failed',
                logContext: [
                    'chat_id' => $chatId,
                    'messenger' => MessengerChannel::Telegram->value,
                ],
            )) {
                $sentCount++;
            }
        }

        return $sentCount;
    }

    /**
     * Объединяет TELEGRAM_REPORT_* и telegram-пользователей с адресом доставки.
     *
     * @return list<int>
     */
    private function resolveRecipientChatIds(): array
    {
        $configuredChatIds = array_values(array_map(
            intval(...),
            (array) $this->config->get('telegram.report.chat_ids', []),
        ));
        $configuredUserIds = array_values(array_map(
            intval(...),
            (array) $this->config->get('telegram.report.user_ids', []),
        ));
        $deliveryChatIds = $this->maxUserRepository
            ->listExternalUserIdsWithDeliveryAddress(MessengerChannel::Telegram);

        return array_values(array_unique(array_merge(
            $configuredChatIds,
            $configuredUserIds,
            $deliveryChatIds,
        )));
    }

    /**
     * Проверяет, настроен ли бот для отправки уведомлений.
     */
    private function isBotConfigured(): bool
    {
        return trim((string) $this->config->get('telegram.bot_token', '')) !== '';
    }
}
