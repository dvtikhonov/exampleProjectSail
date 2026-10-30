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

        $profileKeys = array_values(array_intersect(
            array_keys($userPayload),
            ['user_id', 'first_name', 'last_name', 'username', 'name', 'language_code'],
        ));

        $savedMaxUserId = null;

        if ($userId > 0) {
            $defaultCategoryId = $this->customerCategoryRepository->findOrCreateDefaultCategoryId();
            $user = $this->maxUserRepository->upsertFromMaxBotProfile(
                maxUserId: $userId,
                firstName: $this->resolveFirstName($userPayload),
                lastName: $this->nullableString($userPayload['last_name'] ?? null),
                username: $this->nullableString($userPayload['username'] ?? null),
                languageCode: $this->nullableString($userPayload['language_code'] ?? null),
                defaultCustomerCategoryId: $defaultCategoryId,
            );
            $savedMaxUserId = $user->maxUserId;

            $this->recipientRegistry->rememberUserId($userId);
            $this->greetingSender->sendToUser($userId);
            $this->greetingTracker->markSent($userId);
        }

        // #region agent log
        $this->logger->info('bot_started persist decision', [
            'user_id' => $userId,
            'repository_upsert_invoked' => $savedMaxUserId !== null,
            'max_user_id' => $savedMaxUserId,
            'profile_keys' => $profileKeys,
            'hypothesisId' => 'A',
            'runId' => 'post-fix',
        ]);
        $this->logger->info('bot_started finished', [
            'user_id' => $userId,
            'repository_upsert_invoked' => $savedMaxUserId !== null,
            'max_user_id' => $savedMaxUserId,
            'hypothesisId' => 'C',
            'runId' => 'post-fix',
        ]);
        // #endregion
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
