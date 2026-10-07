<?php

declare(strict_types=1);

namespace App\Infrastructure\Laravel;

use __PHP_Incomplete_Class;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Queue\CallQueuedHandler;

/**
 * Laravel 13 вызывает {@see CallQueuedHandler::commandShouldBeDebounced()} до проверки
 * incomplete class — доступ к свойству даёт ErrorException и рвёт match-job.
 * Перед unserialize подгружаем класс по commandName; debounce пропускаем для incomplete.
 */
final class SafeCallQueuedHandler extends CallQueuedHandler
{
    /**
     * {@inheritDoc}
     */
    public function call(Job $job, array $data)
    {
        $this->preloadCommandClass($data);

        return parent::call($job, $data);
    }

    /**
     * {@inheritDoc}
     */
    public function failed(array $data, $e, string $uuid, ?Job $job = null)
    {
        $this->preloadCommandClass($data);

        parent::failed($data, $e, $uuid, $job);
    }

    /**
     * {@inheritDoc}
     */
    protected function commandShouldBeDebounced($command)
    {
        if ($command instanceof __PHP_Incomplete_Class) {
            return false;
        }

        return parent::commandShouldBeDebounced($command);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function preloadCommandClass(array $data): void
    {
        $commandName = $data['commandName'] ?? null;
        if (! is_string($commandName) || $commandName === '') {
            return;
        }

        class_exists($commandName);
    }
}
