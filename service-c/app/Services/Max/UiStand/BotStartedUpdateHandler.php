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
use Throwable;

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
        $this->agentDebugLog('C', 'BotStartedUpdateHandler.php:handle', 'greeting flag before upsert', [
            'hypothesisId' => 'C',
            'userId' => $userId,
            'wasSent' => $wasSent,
        ]);
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
            $sent = false;
            // #region agent log
            $this->agentDebugLog('C', 'BotStartedUpdateHandler.php:handle', 'greeting branch', [
                'hypothesisId' => 'C',
                'userId' => $userId,
                'wasSent' => $wasSent,
                'willSend' => ! $wasSent,
            ]);
            // #endregion
            if (! $wasSent) {
                $this->greetingTracker->markSent($userId);
                try {
                    $this->greetingSender->sendToUser($userId);
                    $sent = true;
                    // #region agent log
                    $this->agentDebugLog('D', 'BotStartedUpdateHandler.php:handle', 'greeting send returned', [
                        'hypothesisId' => 'D',
                        'userId' => $userId,
                        'sent' => true,
                    ]);
                    // #endregion
                } catch (Throwable $exception) {
                    $this->greetingTracker->forget($userId);
                    // #region agent log
                    $this->agentDebugLog('D', 'BotStartedUpdateHandler.php:handle', 'greeting send threw', [
                        'hypothesisId' => 'D',
                        'userId' => $userId,
                        'errorClass' => $exception::class,
                    ]);
                    // #endregion
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

    /**
     * @param  array<string, mixed>  $data
     */
    private function agentDebugLog(string $hypothesisId, string $location, string $message, array $data): void
    {
        // #region agent log
        $payload = json_encode([
            'sessionId' => 'ff9c5e',
            'runId' => 'pre-fix',
            'hypothesisId' => $hypothesisId,
            'location' => $location,
            'message' => $message,
            'data' => $data,
            'timestamp' => (int) round(microtime(true) * 1000),
        ], JSON_UNESCAPED_UNICODE);
        if ($payload === false) {
            return;
        }
        $line = $payload."\n";
        foreach ([
            '/home/ddd/exampleProjectSail/.cursor/debug-ff9c5e.log',
            '/var/www/html/storage/logs/debug-ff9c5e.log',
            '/home/ddd/exampleProjectSail/service-c/storage/logs/debug-ff9c5e.log',
        ] as $path) {
            $fh = @fopen($path, 'ab');
            if ($fh === false) {
                continue;
            }
            fwrite($fh, $line);
            fclose($fh);
        }
        // #endregion
    }
}
