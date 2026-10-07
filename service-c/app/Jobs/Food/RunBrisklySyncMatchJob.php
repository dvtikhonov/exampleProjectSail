<?php

declare(strict_types=1);

namespace App\Jobs\Food;

use App\Contracts\Food\BrisklySync\BrisklySyncSessionServiceInterface;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Handshake start Cursor match (каталог + POST /match); LLM wait не в этом job.
 */
class RunBrisklySyncMatchJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    /** Без ретраев: повторный LLM-вызов дорогой и может гонять статус. */
    public int $tries = 1;

    /** Таймаут одной попытки (секунды); задаётся в конструкторе. */
    public int $timeout;

    /** Unique-lock чуть дольше timeout, чтобы не запустить второй match той же сессии. */
    public int $uniqueFor;

    /**
     * @param  string  $sessionId  UUID сессии
     * @param  int  $timeoutSeconds  лимит выполнения job
     */
    public function __construct(
        public readonly string $sessionId,
        int $timeoutSeconds,
    ) {
        $this->timeout = max(1, $timeoutSeconds);
        $this->uniqueFor = $this->timeout + 60;
    }

    /**
     * Ключ уникальности — одна сессия, один match.
     */
    public function uniqueId(): string
    {
        return $this->sessionId;
    }

    /**
     * Выполняет match, если сессия ещё в matching.
     */
    public function handle(BrisklySyncSessionServiceInterface $sessions): void
    {
        $sessions->performQueuedMatch($this->sessionId);
    }

    /**
     * Timeout/падение воркера: не оставляем сессию в matching.
     */
    public function failed(?Throwable $exception): void
    {
        app(BrisklySyncSessionServiceInterface::class)->failQueuedMatch($this->sessionId);
    }
}
