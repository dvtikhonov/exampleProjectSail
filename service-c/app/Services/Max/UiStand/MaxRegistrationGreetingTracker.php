<?php

declare(strict_types=1);

namespace App\Services\Max\UiStand;

use App\Contracts\Max\MaxRegistrationGreetingTrackerInterface;
use App\Contracts\Shared\CacheStoreInterface;
use Psr\Log\LoggerInterface;

/**
 * Кэш-пометка об отправленном приветствии регистрации MAX.
 */
final class MaxRegistrationGreetingTracker implements MaxRegistrationGreetingTrackerInterface
{
    /**
     * v2: старый ключ без версии оставался true после сбоя доставки и блокировал кнопку.
     */
    private const string KEY_PREFIX = 'max.registration_greeting.sent.v2.';

    /**
     * Короткое окно на chat_id: второй webhook того же старта не шлёт второе «Привет».
     * Бессрочный ключ user_id это окно не заменяет — он уже блокировал доставку в другой диалог.
     */
    private const string CHAT_KEY_PREFIX = 'max.registration_greeting.chat.';

    private const int CHAT_CLAIM_TTL_SECONDS = 30;

    public function __construct(
        private readonly CacheStoreInterface $cache,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function wasSent(int $userId): bool
    {
        if ($userId <= 0) {
            return false;
        }

        $key = $this->key($userId);
        $raw = $this->cache->get($key);
        $legacy = $this->cache->get('max.registration_greeting.sent.'.$userId);
        $result = $raw === true;

        // #region agent log
        $this->logger->info('bot_started greeting cache', [
            'user_id' => $userId,
            'key' => $key,
            'raw_type' => get_debug_type($raw),
            'raw_is_true' => $result,
            'legacy_is_true' => $legacy === true,
        ]);
        $this->agentDebugLog('A', 'MaxRegistrationGreetingTracker.php:wasSent', 'raw greeting cache value', [
            'userId' => $userId,
            'key' => $key,
            'rawType' => get_debug_type($raw),
            'rawIsTrue' => $result,
            'legacyIsTrue' => $legacy === true,
        ]);
        // #endregion

        return $result;
    }

    /**
     * {@inheritDoc}
     */
    public function markSent(int $userId): void
    {
        if ($userId <= 0) {
            return;
        }

        $this->cache->forever($this->key($userId), true);
    }

    /**
     * {@inheritDoc}
     */
    public function forget(int $userId): void
    {
        if ($userId <= 0) {
            return;
        }

        $this->cache->forget($this->key($userId));
    }

    /**
     * {@inheritDoc}
     */
    public function claimChatDelivery(int $chatId): bool
    {
        if ($chatId <= 0) {
            return false;
        }

        return $this->cache->add($this->chatKey($chatId), true, self::CHAT_CLAIM_TTL_SECONDS);
    }

    /**
     * {@inheritDoc}
     */
    public function releaseChatDelivery(int $chatId): void
    {
        if ($chatId <= 0) {
            return;
        }

        $this->cache->forget($this->chatKey($chatId));
    }

    /**
     * Ключ кэша для user_id MAX.
     */
    private function key(int $userId): string
    {
        return self::KEY_PREFIX.$userId;
    }

    /**
     * Ключ короткого занятия диалога bot_started.
     */
    private function chatKey(int $chatId): string
    {
        return self::CHAT_KEY_PREFIX.$chatId;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function agentDebugLog(string $hypothesisId, string $location, string $message, array $data): void
    {
        // #region agent log
        $payload = json_encode([
            'sessionId' => 'eca1cc',
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
            '/home/ddd/exampleProjectSail/.cursor/debug-eca1cc.log',
            '/var/www/html/storage/logs/debug-eca1cc.log',
            '/home/ddd/exampleProjectSail/service-c/storage/logs/debug-eca1cc.log',
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
