<?php

declare(strict_types=1);

namespace App\Contracts\Food\BrisklySync;

/**
 * Порт постановки match-задачи в очередь (без Illuminate в сервисах).
 */
interface BrisklySyncMatchQueueInterface
{
    /**
     * Ставит performQueuedMatch для сессии (handshake start-job).
     */
    public function dispatch(string $sessionId): void;

    /**
     * Delayed ExpireBrisklySyncMatchJob (llm_timeout).
     */
    public function dispatchExpire(string $sessionId, string $matchGeneration): void;
}
