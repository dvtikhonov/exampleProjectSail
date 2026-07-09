<?php

declare(strict_types=1);

namespace App\Services\Max\UiStand;

use App\Contracts\Max\MaxMenuAvailabilityNotifierInterface;
use App\Contracts\Max\MaxOrderNotificationConfigProviderInterface;
use App\Contracts\Max\MaxUserRepositoryInterface;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Support\Facades\Log;
use Shared\MaxMessenger\Contracts\MaxMessengerClientInterface;
use Shared\MaxMessenger\DTO\MaxMessageDto;
use Shared\MaxMessenger\Exceptions\MaxMessengerException;
use Throwable;

/**
 * Уведомление в MAX о доступности меню на сегодня.
 *
 * Получатели: MAX_REPORT_* и пользователи max_users с сохранённым адресом доставки.
 */
class MaxMenuAvailabilityNotifier implements MaxMenuAvailabilityNotifierInterface
{
    private const string TIMEZONE = 'Europe/Moscow';

    public function __construct(
        private readonly MaxMessengerClientInterface $client,
        private readonly MaxOrderNotificationConfigProviderInterface $configProvider,
        private readonly MaxUserRepositoryInterface $maxUserRepository,
        private readonly Repository $config,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function notify(): int
    {
        if (! $this->isBotConfigured()) {
            Log::channel('messMax')->warning('MAX menu availability notification skipped: bot is not configured');

            return 0;
        }

        $notificationConfig = $this->configProvider->config();
        $userIds = $this->resolveRecipientUserIds($notificationConfig->userIds);

        if ($notificationConfig->chatIds === [] && $userIds === []) {
            Log::channel('messMax')->warning('MAX menu availability notification skipped: recipients are not configured');

            return 0;
        }

        $text = self::messageTextForDate(CarbonImmutable::now(self::TIMEZONE));
        $sentCount = 0;

        foreach ($notificationConfig->chatIds as $chatId) {
            if ($this->trySendMessage($text, chatId: $chatId)) {
                $sentCount++;
            }
        }

        foreach ($userIds as $userId) {
            if ($this->trySendMessage($text, userId: $userId)) {
                $sentCount++;
            }
        }

        return $sentCount;
    }

    /**
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

    private function isBotConfigured(): bool
    {
        $botUsername = trim((string) $this->config->get('max.bot_username', ''));
        $botAccessToken = trim((string) $this->config->get('max.bot_access_token', ''));

        return $botUsername !== '' && $botAccessToken !== '';
    }

    private function trySendMessage(string $text, ?int $chatId = null, ?int $userId = null): bool
    {
        try {
            $this->client->sendMessage(new MaxMessageDto(
                text: $text,
                chatId: $chatId,
                userId: $userId,
            ));
        } catch (MaxMessengerException $exception) {
            Log::channel('messMax')->warning('MAX menu availability notification send failed', [
                'chat_id' => $chatId,
                'user_id' => $userId,
                'error' => $exception->userMessage(),
            ]);

            return false;
        } catch (Throwable $exception) {
            Log::channel('messMax')->warning('MAX menu availability notification send failed', [
                'chat_id' => $chatId,
                'user_id' => $userId,
                'error' => $exception->getMessage(),
            ]);

            return false;
        }

        return true;
    }
}
