<?php

declare(strict_types=1);

namespace App\Jobs\Food;

use App\Contracts\Food\BrisklySync\BrisklySyncSessionServiceInterface;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * LLM-watchdog: если сессия всё ещё matching с тем же generation — Failed + abort.
 */
class ExpireBrisklySyncMatchJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 30;

    public int $uniqueFor;

    /**
     * @param  string  $sessionId  UUID сессии
     * @param  string  $matchGeneration  generation handshake
     * @param  int  $uniqueForSeconds  окно unique ≈ llm_timeout + 60
     * @param  int  $delaySeconds  отложить expire (llm_timeout)
     */
    public function __construct(
        public readonly string $sessionId,
        public readonly string $matchGeneration,
        int $uniqueForSeconds,
        int $delaySeconds,
    ) {
        $this->uniqueFor = max(1, $uniqueForSeconds);
        $this->delay = max(0, $delaySeconds);
    }

    /**
     * Unique на пару session+generation, чтобы rematch мог поставить новый expire.
     */
    public function uniqueId(): string
    {
        return $this->sessionId.':'.$this->matchGeneration;
    }

    /**
     * Снимает зависший matching, если generation ещё актуален.
     */
    public function handle(BrisklySyncSessionServiceInterface $sessions): void
    {
        $sessions->expireQueuedMatch($this->sessionId, $this->matchGeneration);
    }
}
