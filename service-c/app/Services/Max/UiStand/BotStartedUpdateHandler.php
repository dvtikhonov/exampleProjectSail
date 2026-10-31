<?php

declare(strict_types=1);

namespace App\Services\Max\UiStand;

use App\Contracts\Food\Delivery\CustomerCategoryRepositoryInterface;
use App\Contracts\Max\MaxRegistrationGreetingTrackerInterface;
use App\Contracts\Max\MaxUiStandGreetingSenderInterface;
use App\Contracts\Max\MaxUiStandRecipientRegistryInterface;
use App\Contracts\Max\MaxUserIdentityRepositoryInterface;
use App\Contracts\Max\MaxWebhookUpdateHandlerInterface;
use Psr\Log\LoggerInterface;

/**
 * Обработка webhook-события bot_started: upsert в max_users и приветствие.
 */
final class BotStartedUpdateHandler implements MaxWebhookUpdateHandlerInterface
{
    public function __construct(
        private readonly CustomerCategoryRepositoryInterface $customerCategoryRepository,
        private readonly MaxUserIdentityRepositoryInterface $maxUserRepository,
        private readonly MaxRegistrationGreetingTrackerInterface $greetingTracker,
        private readonly MaxUiStandGreetingSenderInterface $greetingSender,
        private readonly MaxUiStandRecipientRegistryInterface $recipientRegistry,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * {@inheritdoc}
     */
    public function handle(array $payload): void
    {
        $userPayload = isset($payload['user']) && is_array($payload['user']) ? $payload['user'] : [];
        $userId = $this->resolveUserId($payload, $userPayload);

        $this->logger->info('bot_started', ['user_id' => $userId]);

        $wasSent = $userId > 0 && $this->greetingTracker->wasSent($userId);

        // #region agent log
        $agentLine = json_encode([
            'sessionId' => 'a0ff56',
            'runId' => 'post-fix',
            'hypothesisId' => 'A',
            'location' => 'BotStartedUpdateHandler.php:handle',
            'message' => 'bot_started greeting decision',
            'data' => [
                'userId' => $userId,
                'wasSent' => $wasSent,
                'willSend' => $userId > 0 && ! $wasSent,
                'payloadTimestamp' => $payload['timestamp'] ?? null,
            ],
            'timestamp' => (int) round(microtime(true) * 1000),
        ], JSON_UNESCAPED_UNICODE);
        if (is_string($agentLine)) {
            foreach ([
                '/home/ddd/exampleProjectSail/.cursor/debug-a0ff56.log',
                '/var/www/html/storage/logs/debug-a0ff56.log',
            ] as $agentLogPath) {
                @file_put_contents($agentLogPath, $agentLine."\n", FILE_APPEND);
            }
        }
        // #endregion

        if ($userId > 0) {
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
            if (! $wasSent) {
                $this->greetingSender->sendToUser($userId);
                $this->greetingTracker->markSent($userId);
            }
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
