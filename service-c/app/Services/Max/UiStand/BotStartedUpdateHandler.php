<?php

declare(strict_types=1);

namespace App\Services\Max\UiStand;

use App\Contracts\Max\MaxUiStandGreetingSenderInterface;
use App\Contracts\Max\MaxUiStandRecipientRegistryInterface;
use App\Contracts\Max\MaxWebhookUpdateHandlerInterface;
use Psr\Log\LoggerInterface;

/**
 * Обработка webhook-события bot_started.
 */
final class BotStartedUpdateHandler implements MaxWebhookUpdateHandlerInterface
{
    public function __construct(
        private readonly MaxUiStandGreetingSenderInterface $greetingSender,
        private readonly MaxUiStandRecipientRegistryInterface $recipientRegistry,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * {@inheritdoc}
     */
    public function handle(array $payload): void
    {
        $userId = 0;

        if (isset($payload['user']['user_id'])) {
            $userId = (int) $payload['user']['user_id'];
        } elseif (isset($payload['user_id'])) {
            $userId = (int) $payload['user_id'];
        }

        $this->logger->info('bot_started', ['user_id' => $userId]);

        // #region agent log
        $userPayload = isset($payload['user']) && is_array($payload['user']) ? $payload['user'] : [];
        $profileKeys = array_values(array_intersect(
            array_keys($userPayload),
            ['user_id', 'first_name', 'last_name', 'username', 'name', 'language_code'],
        ));
        $this->logger->info('bot_started persist decision', [
            'user_id' => $userId,
            'repository_upsert_invoked' => false,
            'profile_keys' => $profileKeys,
            'hypothesisId' => 'A',
        ]);
        // #endregion

        if ($userId > 0) {
            $this->recipientRegistry->rememberUserId($userId);
            $this->greetingSender->sendToUser($userId);

            // #region agent log
            $this->logger->info('bot_started finished without repository upsert', [
                'user_id' => $userId,
                'hypothesisId' => 'C',
            ]);
            // #endregion
        }
    }
}
