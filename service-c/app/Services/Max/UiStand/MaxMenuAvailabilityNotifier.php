<?php

declare(strict_types=1);

namespace App\Services\Max\UiStand;

use App\Contracts\Max\MaxMenuAvailabilityNotifierInterface;
use App\Contracts\Max\MaxMessengerNotificationSenderInterface;
use App\Contracts\Max\MaxOrderNotificationConfigProviderInterface;
use App\Contracts\Max\MaxUserRepositoryInterface;
use App\Contracts\Shared\ApplicationConfigInterface;
use Carbon\CarbonImmutable;
use Psr\Log\LoggerInterface;

/**
 * Уведомление в MAX о доступности меню на дату «Блюда на».
 *
 * Получатели: MAX_REPORT_* и пользователи max_users с сохранённым адресом доставки.
 */
class MaxMenuAvailabilityNotifier implements MaxMenuAvailabilityNotifierInterface
{
    private const string TIMEZONE = 'Europe/Moscow';

    public function __construct(
        private readonly MaxOrderNotificationConfigProviderInterface $configProvider,
        private readonly MaxUserRepositoryInterface $maxUserRepository,
        private readonly ApplicationConfigInterface $config,
        private readonly MaxMessengerNotificationSenderInterface $notificationSender,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function notify(CarbonImmutable $menuDate): int
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
     * Определяет получателей уведомления о доступности меню.
     *
     * @param  list<int>  $configuredUserIds
     * @return list<int>
     */
    private function resolveRecipientUserIds(array $configuredUserIds): array
    {
        $deliveryUserIds = $this->maxUserRepository->listMaxUserIdsWithDeliveryAddress();

        return array_values(array_unique(array_merge($configuredUserIds, $deliveryUserIds)));
    }

    /**
     * Формирует текст уведомления для указанной даты (MSK).
     */
    public static function messageTextForDate(CarbonImmutable $date): string
    {
        return sprintf(
            'Доступно для заказов меню на %s',
            $date->timezone(self::TIMEZONE)->format('d.m.Y'),
        );
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
