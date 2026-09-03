<?php

declare(strict_types=1);

namespace App\Contracts\Shared;

/**
 * Порт постановки задач в очередь без прямого вызова Job::dispatch().
 */
interface JobDispatcherInterface
{
    /**
     * Ставит задачу в очередь (или выполняет синхронно — зависит от драйвера).
     *
     * @param  object  $job  Экземпляр job (создаётся в delivery/adapters слое или сервисе)
     */
    public function dispatch(object $job): void;
}
