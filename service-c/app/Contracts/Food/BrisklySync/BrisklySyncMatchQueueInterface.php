<?php

declare(strict_types=1);

namespace App\Contracts\Food\BrisklySync;

/**
 * Порт постановки match-задачи в очередь (без Illuminate в сервисах).
 */
interface BrisklySyncMatchQueueInterface
{
    /**
     * Ставит performQueuedMatch для сессии.
     */
    public function dispatch(string $sessionId): void;
}
