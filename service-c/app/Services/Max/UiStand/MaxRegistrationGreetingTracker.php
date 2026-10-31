<?php

declare(strict_types=1);

namespace App\Services\Max\UiStand;

use App\Contracts\Max\MaxRegistrationGreetingTrackerInterface;
use App\Contracts\Shared\CacheStoreInterface;

/**
 * Кэш-пометка об отправленном приветствии регистрации MAX.
 */
final class MaxRegistrationGreetingTracker implements MaxRegistrationGreetingTrackerInterface
{
    private const string KEY_PREFIX = 'max.registration_greeting.sent.';

    public function __construct(
        private readonly CacheStoreInterface $cache,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function wasSent(int $userId): bool
    {
        if ($userId <= 0) {
            return false;
        }

        $raw = $this->cache->get($this->key($userId));
        $result = $raw === true;
        // #region agent log
        $this->agentDebugLog('E', 'MaxRegistrationGreetingTracker.php:wasSent', 'raw greeting cache value', [
            'hypothesisId' => 'E',
            'userId' => $userId,
            'rawType' => get_debug_type($raw),
            'rawIsTrue' => $raw === true,
            'result' => $result,
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
     * Ключ кэша для user_id MAX.
     */
    private function key(int $userId): string
    {
        return self::KEY_PREFIX.$userId;
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
