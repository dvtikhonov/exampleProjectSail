<?php

declare(strict_types=1);

namespace App\Services\Max\UiStand;

use App\Contracts\Food\Delivery\CustomerCategoryRepositoryInterface;
use App\Contracts\Max\MaxRegistrationGreetingTrackerInterface;
use App\Contracts\Max\MaxUiStandGreetingSenderInterface;
use App\Contracts\Max\MaxUiStandRecipientRegistryInterface;
use App\Contracts\Max\MaxUserIdentityRepositoryInterface;
use App\Contracts\Max\MaxWebhookUpdateHandlerInterface;
use App\Contracts\Shared\CacheStoreInterface;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Обработка webhook-события bot_started: upsert в max_users и приветствие.
 */
final class BotStartedUpdateHandler implements MaxWebhookUpdateHandlerInterface
{
    /**
     * Счётчик webhook bot_started без валидного user_id (observability, без ретраев MAX).
     */
    public const string INVALID_PAYLOAD_COUNTER_KEY = 'max.webhook.bot_started.invalid_payload';

    public function __construct(
        private readonly CustomerCategoryRepositoryInterface $customerCategoryRepository,
        private readonly MaxUserIdentityRepositoryInterface $maxUserRepository,
        private readonly MaxRegistrationGreetingTrackerInterface $greetingTracker,
        private readonly MaxUiStandGreetingSenderInterface $greetingSender,
        private readonly MaxUiStandRecipientRegistryInterface $recipientRegistry,
        private readonly CacheStoreInterface $cache,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * {@inheritdoc}
     */
    public function handle(array $payload): void
    {
        $userPayload = isset($payload['user']) && is_array($payload['user']) ? $payload['user'] : [];
        $userId = $this->resolveUserId($payload, $userPayload);
        $chatId = isset($payload['chat_id']) ? (int) $payload['chat_id'] : 0;

        if ($userId <= 0) {
            $this->recordInvalidPayload($userId, $chatId, isset($payload['user']));

            return;
        }

        $this->logger->info('bot_started', ['user_id' => $userId]);

        $wasSent = $this->greetingTracker->wasSent($userId);

        $defaultCategoryId = $this->customerCategoryRepository->findOrCreateDefaultCategoryId();
        $this->maxUserRepository->upsertFromMaxBotProfile(
            maxUserId: $userId,
            firstName: $this->resolveFirstName($userPayload),
            lastName: $this->nullableString($userPayload['last_name'] ?? null),
            username: $this->nullableString($userPayload['username'] ?? null),
            languageCode: $this->nullableString($userPayload['language_code'] ?? null),
            defaultCustomerCategoryId: $defaultCategoryId,
        );

        $this->recipientRegistry->rememberUserId($userId);
        $sent = false;
        if ($chatId > 0) {
            $claimed = $this->greetingTracker->claimChatDelivery($chatId);
            if ($claimed) {
                try {
                    $this->greetingSender->sendToChat($chatId);
                    $this->greetingTracker->markSent($userId);
                    $sent = true;
                } catch (Throwable $exception) {
                    $this->greetingTracker->releaseChatDelivery($chatId);
                    $this->logger->info('bot_started greeting', [
                        'user_id' => $userId,
                        'was_sent' => $wasSent,
                        'sent' => false,
                    ]);

                    throw $exception;
                }
            }
        } elseif (! $wasSent) {
            $this->greetingTracker->markSent($userId);
            try {
                $this->greetingSender->sendToUser($userId);
                $sent = true;
            } catch (Throwable $exception) {
                $this->greetingTracker->forget($userId);
                $this->logger->info('bot_started greeting', [
                    'user_id' => $userId,
                    'was_sent' => $wasSent,
                    'sent' => false,
                ]);

                throw $exception;
            }
        }

        $this->logger->info('bot_started greeting', [
            'user_id' => $userId,
            'was_sent' => $wasSent,
            'sent' => $sent,
        ]);
    }

    /**
     * Warning + счётчик: HTTP 200 сохраняем (контракт MAX), но ветка не «тихая».
     */
    private function recordInvalidPayload(int $userId, int $chatId, bool $hasUser): void
    {
        $this->logger->warning('bot_started invalid payload', [
            'user_id' => $userId,
            'chat_id' => $chatId,
            'has_user' => $hasUser,
        ]);

        try {
            $this->cache->increment(self::INVALID_PAYLOAD_COUNTER_KEY);
        } catch (Throwable $exception) {
            $this->logger->warning('bot_started invalid payload counter failed', [
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
                'cache_key' => self::INVALID_PAYLOAD_COUNTER_KEY,
            ]);
        }
    }

    /**
     * Достаёт user_id из объекта user либо с верхнего уровня payload.
     *
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $userPayload
     */
    private function resolveUserId(array $payload, array $userPayload): int
    {
        if (isset($userPayload['user_id'])) {
            return (int) $userPayload['user_id'];
        }

        if (isset($payload['user_id'])) {
            return (int) $payload['user_id'];
        }

        return 0;
    }

    /**
     * Имя для max_users: first_name, иначе устаревшее name, иначе заглушка.
     *
     * @param  array<string, mixed>  $userPayload
     */
    private function resolveFirstName(array $userPayload): string
    {
        $firstName = $this->nullableString($userPayload['first_name'] ?? null);
        if ($firstName !== null) {
            return $firstName;
        }

        $legacyName = $this->nullableString($userPayload['name'] ?? null);

        return $legacyName ?? 'User';
    }

    /**
     * Непустая строка либо null.
     */
    private function nullableString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
