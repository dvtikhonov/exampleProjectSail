<?php

declare(strict_types=1);

namespace App\Infrastructure\Laravel;

use App\Contracts\Food\BrisklySync\BrisklySyncSessionServiceInterface;
use App\Jobs\Food\RunBrisklySyncMatchJob;
use Illuminate\Queue\Events\JobFailed;
use Throwable;

/**
 * Если match-job не дошёл до {@see RunBrisklySyncMatchJob::failed()} (incomplete class),
 * всё равно снимаем сессию с matching.
 */
final class FailBrisklySyncMatchOnQueueJobFailed
{
    public function __construct(
        private readonly BrisklySyncSessionServiceInterface $sessions,
    ) {}

    /**
     * Помечает matching-сессию failed по payload job.
     */
    public function handle(JobFailed $event): void
    {
        $payload = [];
        try {
            $payload = $event->job->payload();
        } catch (Throwable) {
            return;
        }

        if (($payload['displayName'] ?? null) !== RunBrisklySyncMatchJob::class) {
            return;
        }

        $sessionId = $this->sessionIdFromPayload($payload);
        if ($sessionId === null) {
            return;
        }

        $this->sessions->failQueuedMatch($sessionId);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function sessionIdFromPayload(array $payload): ?string
    {
        $command = $payload['data']['command'] ?? null;
        if (! is_string($command) || $command === '') {
            return null;
        }

        if (preg_match('/s:9:"sessionId";s:36:"([0-9a-f-]{36})"/', $command, $matches) !== 1) {
            return null;
        }

        return $matches[1];
    }
}
