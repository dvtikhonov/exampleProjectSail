<?php

declare(strict_types=1);

namespace App\Services\Max\UiStand;

use App\Contracts\Max\MaxMenuAvailabilityNotifierInterface;
use App\Contracts\Max\MaxMessengerNotificationSenderInterface;
use App\Contracts\Max\MaxOrderNotificationConfigProviderInterface;
use App\Contracts\Max\MaxUserDeliveryRepositoryInterface;
use App\Contracts\Shared\ApplicationConfigInterface;
use App\Enums\MessengerChannel;
use App\Services\Food\Menu\MenuAvailabilityClientNotificationText;
use DateTimeInterface;
use Psr\Log\LoggerInterface;

/**
 * Уведомление в MAX о доступности меню на дату «Блюда на».
 *
 * Получатели: MAX_REPORT_* и пользователи messenger=max с сохранённым адресом доставки.
 */
class MaxMenuAvailabilityNotifier implements MaxMenuAvailabilityNotifierInterface
{
    public function __construct(
        private readonly MaxOrderNotificationConfigProviderInterface $configProvider,
        private readonly MaxUserDeliveryRepositoryInterface $maxUserRepository,
        private readonly ApplicationConfigInterface $config,
        private readonly MaxMessengerNotificationSenderInterface $notificationSender,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function notify(DateTimeInterface $menuDate): int
    {
        if (! $this->isBotConfigured()) {
            $this->logger->warning('MAX menu availability notification skipped: bot is not configured');

            return 0;
        }

        $notificationConfig = $this->configProvider->config();
        $userIds = $this->resolveRecipientUserIds($notificationConfig->userIds);

        if ($notificationConfig->chatIds === [] && $userIds === []) {
            $this->logger->warning('MAX menu availability notification skipped: recipients are not configured');

            return 0;
        }

        $text = self::messageTextForDate($menuDate);
        $sentCount = 0;

        foreach ($notificationConfig->chatIds as $chatId) {
            if ($this->notificationSender->send(
                text: $text,
                chatId: $chatId,
                failureLogMessage: 'MAX menu availability notification send failed',
                logContext: [
                    'chat_id' => $chatId,
                    'user_id' => null,
                ],
            )) {
                $sentCount++;
            }
        }

        foreach ($userIds as $userId) {
            if ($this->notificationSender->send(
                text: $text,
                userId: $userId,
                failureLogMessage: 'MAX menu availability notification send failed',
                logContext: [
                    'chat_id' => null,
                    'user_id' => $userId,
                ],
            )) {
                $sentCount++;
            }
        }

        return $sentCount;
    }

    /**
     * Определяет получателей уведомления о доступности меню (только канал MAX).
     *
     * @param  list<int>  $configuredUserIds
     * @return list<int>
     */
    private function resolveRecipientUserIds(array $configuredUserIds): array
    {
        $deliveryUserIds = $this->maxUserRepository
            ->listExternalUserIdsWithDeliveryAddress(MessengerChannel::Max);

        return array_values(array_unique(array_merge($configuredUserIds, $deliveryUserIds)));
    }

    /**
     * Формирует текст уведомления для указанной даты (MSK).
     */
    public static function messageTextForDate(DateTimeInterface $date): string
    {
        return MenuAvailabilityClientNotificationText::forDate($date);
    }

    /**
     * Проверяет, настроен ли бот для отправки уведомлений.
     */
    private function isBotConfigured(): bool
    {
        $botUsername = trim((string) $this->config->get('max.bot_username', ''));
        $botAccessToken = trim((string) $this->config->get('max.bot_access_token', ''));

        return $botUsername !== '' && $botAccessToken !== '';
    }
}
