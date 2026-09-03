<?php

declare(strict_types=1);

namespace App\Contracts\Shared;

/**
 * Порт управления транзакциями БД для use-case слоя без прямой зависимости от Laravel DB.
 */
interface TransactionManagerInterface
{
    /**
     * Выполняет callback внутри транзакции БД.
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public function run(callable $callback): mixed;
}
