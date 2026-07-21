<?php

declare(strict_types=1);

namespace App\Services\Max\UiStand;

use App\Contracts\Food\DailyMenuLineCollectorInterface;
use App\Contracts\Food\FoodOrderAdminRepositoryInterface;
use App\Contracts\Food\MaxManagerDailyMenuMessageBuilderInterface;
use App\Contracts\Max\MaxManagerDailyMenuNotifierInterface;
use App\Enums\Food\FoodOrderAdminRole;
use App\Support\MaxUiStandRecipientResolver;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Support\Facades\Log;
use Shared\MaxMessenger\Contracts\MaxMessengerClientInterface;
use Shared\MaxMessenger\DTO\MaxMessageDto;
use Shared\MaxMessenger\Exceptions\MaxMessengerException;
use Throwable;

/**
 * Два уведомления о меню дня пользователям с ролью max_manager.
 *
 * Сначала DM на max_user_id менеджера; при ошибке MAX — fallback в MAX_UI_STAND_*
 * (как у уведомления «Заказ на …» оформившему ручной заказ).
 */
class MaxManagerDailyMenuNotifier implements MaxManagerDailyMenuNotifierInterface
{
    private const string TIMEZONE = 'Europe/Moscow';

    public function __construct(
        private readonly MaxMessengerClientInterface $client,
        private readonly FoodOrderAdminRepositoryInterface $foodOrderAdminRepository,
        private readonly DailyMenuLineCollectorInterface $lineCollector,
        private readonly MaxManagerDailyMenuMessageBuilderInterface $messageBuilder,
        private readonly MaxUiStandRecipientResolver $uiStandRecipientResolver,
        private readonly Repository $config,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function notify(): int
    {
        if (! $this->isBotConfigured()) {
            Log::channel('messMax')->warning('MAX manager daily menu notification skipped: bot is not configured');

            return 0;
        }

        $managerIds = $this->foodOrderAdminRepository->listActiveMaxUserIdsByRole(
            FoodOrderAdminRole::MaxManager,
        );

        if ($managerIds === []) {
            Log::channel('messMax')->warning('MAX manager daily menu notification skipped: no active max_manager recipients');

            return 0;
        }

        $menuDate = CarbonImmutable::now(self::TIMEZONE)->addDay();
        $messages = $this->messageBuilder->build($menuDate, $this->lineCollector->collect());
        $texts = [$messages->withoutDelivery, $messages->withDelivery];
        $sentCount = 0;

        foreach ($managerIds as $userId) {
            foreach ($texts as $text) {
                if ($this->trySendToUser($text, $userId)) {
                    $sentCount++;

                    continue;
                }

                $sentCount += $this->trySendToUiStand($text, $userId);
            }
        }

        return $sentCount;
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

    /**
     * Fallback: меню дня в UI Stand (chat_id / user_id), если DM менеджеру недоступен.
     *
     * @return int Количество успешно отправленных сообщений
     */
    private function trySendToUiStand(string $text, int $failedUserId): int
    {
        $chatIds = $this->uiStandRecipientResolver->chatIds();
        $userIds = $this->uiStandRecipientResolver->userIds();

        if ($chatIds === [] && $userIds === []) {
            Log::channel('messMax')->warning(
                'MAX manager daily menu notification fallback skipped: UI Stand recipients are not configured',
                ['user_id' => $failedUserId],
            );

            return 0;
        }

        $sentCount = 0;

        foreach ($chatIds as $chatId) {
            if ($this->trySendMessage($text, chatId: $chatId, userId: null, failedUserId: $failedUserId)) {
                $sentCount++;
            }
        }

        foreach ($userIds as $userId) {
            if ($this->trySendMessage($text, chatId: null, userId: $userId, failedUserId: $failedUserId)) {
                $sentCount++;
            }
        }

        return $sentCount;
    }

    /**
     * Пытается отправить одно сообщение в личный диалог менеджера.
     */
    private function trySendToUser(string $text, int $userId): bool
    {
        return $this->trySendMessage($text, chatId: null, userId: $userId);
    }

    /**
     * Пытается отправить сообщение о меню дня в MAX.
     */
    private function trySendMessage(
        string $text,
        ?int $chatId = null,
        ?int $userId = null,
        ?int $failedUserId = null,
    ): bool {
        try {
            $this->client->sendMessage(new MaxMessageDto(
                text: $text,
                chatId: $chatId,
                userId: $userId,
            ));
        } catch (MaxMessengerException $exception) {
            Log::channel('messMax')->warning('MAX manager daily menu notification send failed', [
                'chat_id' => $chatId,
                'user_id' => $userId,
                'failed_user_id' => $failedUserId,
                'error' => $exception->userMessage(),
            ]);

            return false;
        } catch (Throwable $exception) {
            Log::channel('messMax')->warning('MAX manager daily menu notification send failed', [
                'chat_id' => $chatId,
                'user_id' => $userId,
                'failed_user_id' => $failedUserId,
                'error' => $exception->getMessage(),
            ]);

            return false;
        }

        return true;
    }
}
